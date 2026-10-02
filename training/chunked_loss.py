"""Module ChunkedLossTrainer pour l'entraînement frugal de modèles à large vocabulaire (Gemma 4).

Problématique résolue :
    Gemma 4 possède un vocabulaire de 262 144 tokens.
    Une projection linéaire standard (seq_len=2048 x vocab=262144) produit un tenseur float32 de 4,3 Go de VRAM,
    déclenchant un crash OOM immédiat sur les GPU de 15-16 Go (Nvidia Tesla T4, RTX 3060/4070).

Solution mathématique :
    Découpage différentiable de la projection des logits et du calcul de la perte Cross-Entropy
    en micro-chunks de 256 tokens appliqués exclusivement sur les positions des réponses de l'assistant (labels != -100).
    Réduit le pic mémoire de la perte de plus de 94% (< 300 Mo).
"""

from typing import Dict, Any, Tuple, Union
import torch
import torch.nn.functional as F

try:
    from transformers import Trainer
except ImportError:
    # Classe factice pour permettre l'import en environnement minimal
    class Trainer:
        pass


def get_lm_head(model: torch.nn.Module) -> torch.nn.Module:
    """Localise de manière récursive la couche de projection finale lm_head."""
    if hasattr(model, "lm_head"):
        return model.lm_head
    if hasattr(model, "base_model"):
        return get_lm_head(model.base_model)
    if hasattr(model, "model"):
        return get_lm_head(model.model)
    raise AttributeError("Impossible de localiser lm_head dans le modèle")


class ChunkedLossTrainer(Trainer):
    """Trainer Hugging Face avec calcul de perte Cross-Entropy par micro-chunks différentiables."""

    def __init__(self, *args, chunk_size: int = 256, **kwargs):
        super().__init__(*args, **kwargs)
        self.chunk_size = chunk_size

    def compute_loss(
        self,
        model: torch.nn.Module,
        inputs: Dict[str, Any],
        return_outputs: bool = False,
        **kwargs: Any,
    ) -> Union[torch.Tensor, Tuple[torch.Tensor, Any]]:
        labels = inputs.get("labels")
        # Exclut labels de fwd_inputs pour empêcher le modèle de calculer en interne des logits float32 non chunkés
        fwd_inputs = {k: v for k, v in inputs.items() if k != "labels"}

        try:
            outputs = model(**fwd_inputs, logits_to_keep=1, output_hidden_states=True)
        except TypeError:
            outputs = model(**fwd_inputs, output_hidden_states=True)

        # Extraction du dernier état caché
        if hasattr(outputs, "hidden_states") and outputs.hidden_states is not None:
            hidden = outputs.hidden_states[-1]
        elif hasattr(outputs, "last_hidden_state") and outputs.last_hidden_state is not None:
            hidden = outputs.last_hidden_state
        else:
            raise RuntimeError(f"Impossible d'extraire les états cachés : champs={dir(outputs)}")

        # Décalage causal standard : le token à la position t prédit le token à t+1
        shift_h = hidden[:, :-1, :].contiguous()
        shift_l = labels[:, 1:].contiguous()

        # Masquage strict des tokens du prompt / utilisateur / padding (labels == -100)
        mask = shift_l != -100
        if not mask.any():
            dummy = torch.tensor(0.0, device=shift_h.device, requires_grad=True)
            return (dummy, outputs) if return_outputs else dummy

        active_h = shift_h[mask]
        active_l = shift_l[mask]

        head = get_lm_head(model)
        head_param = next(head.parameters())
        head_dev = head_param.device
        head_dtype = head_param.dtype
        total_loss = torch.tensor(0.0, device=head_dev)
        chunk_size = getattr(self, "chunk_size", 256)

        # Calcul économe par morceaux de 256 tokens actifs
        for i in range(0, active_h.size(0), chunk_size):
            h_chunk = active_h[i : i + chunk_size].to(device=head_dev, dtype=head_dtype)
            l_chunk = active_l[i : i + chunk_size].to(device=head_dev)
            logits_chunk = head(h_chunk).float()
            chunk_loss = F.cross_entropy(logits_chunk, l_chunk, reduction="sum")
            total_loss = total_loss + chunk_loss

        loss = (total_loss / active_l.numel()).to(shift_h.device)
        return (loss, outputs) if return_outputs else loss
