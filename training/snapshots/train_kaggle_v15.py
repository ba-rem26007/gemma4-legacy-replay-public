#!/usr/bin/env python3
"""Fine-Tuning Gemma 4 QLoRA autonome sur Kaggle GPU (Tesla T4 15 Go)."""

import os
os.environ["PYTORCH_CUDA_ALLOC_CONF"] = "expandable_segments:True"

import glob
import json
import random
import shutil
import subprocess
import sys
from pathlib import Path

# 1. Installation & Mise à jour des modules requis (transformers>=5.17 pour gemma4)
print("=== INSTALLATION DES MODULES COMPLÉMENTAIRES ===")
subprocess.check_call([
    sys.executable, "-m", "pip", "install", "-q", "--upgrade",
    "transformers>=5.17.0", "accelerate", "bitsandbytes>=0.45.0", "peft>=0.14.0", "datasets", "kagglehub>=0.4.0"
])

import torch
from datasets import Dataset
from peft import LoraConfig, get_peft_model
from transformers import (AutoModelForCausalLM, AutoTokenizer, BitsAndBytesConfig,
                          Trainer, TrainingArguments)

print("=== VÉRIFICATION DU GPU KAGGLE ===")
assert torch.cuda.is_available(), "GPU non disponible !"
num_gpus = torch.cuda.device_count()
print(f"Périphériques GPU détectés : {num_gpus}")
for i in range(num_gpus):
    vram = torch.cuda.get_device_properties(i).total_memory / (1024**3)
    print(f"  GPU {i} ({torch.cuda.get_device_name(i)}) : {vram:.2f} Go VRAM")
print(f"Support bfloat16 : {torch.cuda.is_bf16_supported()}")

# 2. Chargement des trajectoires condensées
print("=== CHARGEMENT DU DATASET DE TRAJECTOIRES ===")
data_files = []
for p in Path("/kaggle/input").rglob("*.jsonl"):
    data_files.append(p)

print(f"Fichiers trouvés : {[str(f) for f in data_files]}")

rows = []
for fpath in data_files:
    print(f"Lecture de {fpath.name}...")
    with open(fpath, "r", encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if line:
                rows.append(json.loads(line))

# Dédoublonnage et équilibrage par bug (max 2 par bug)
per_bug = {}
random.seed(42)
random.shuffle(rows)
filtered = []
for r in rows:
    key = r.get("pr") or r.get("id") or str(hash(r.get("messages", [{}])[0].get("content", "")))
    if len(per_bug.setdefault(key, [])) < 2:
        per_bug[key].append(1)
        filtered.append(r)

print(f"✅ Trajectoires retenues pour l'entraînement : {len(filtered)} exemples purs.")

# 3. Détection du modèle Gemma 4 monté dans /kaggle/input
print("=== LOCALISATION DU MODÈLE GEMMA 4 ===")
model_path = None
candidates = [
    Path("/kaggle/input/models/google/gemma-4/transformers/gemma-4-e4b-it/1"),
    Path("/kaggle/input/gemma-4/transformers/gemma-4-e4b-it/1"),
    Path("/kaggle/input/gemma-4/transformers/gemma-4-e4b-it"),
    Path("/kaggle/input/gemma-4"),
]
for candidate in candidates:
    if candidate.exists() and (candidate / "config.json").exists():
        model_path = str(candidate)
        break

if not model_path:
    # Recherche récursive de config.json hors datasets
    for p in Path("/kaggle/input").rglob("config.json"):
        if "trajectories" not in str(p) and "dataset" not in str(p):
            model_path = str(p.parent)
            break

if not model_path:
    import kagglehub
    try:
        print("Téléchargement via kagglehub...")
        model_path = kagglehub.model_download("google/gemma-4/transformers/gemma-4-e4b-it")
    except Exception as e:
        print(f"Repli Hugging Face : {e}")
        model_path = "google/gemma-4-e4b-it"

print(f"Modèle sélectionné : {model_path}")

# 4. Tokenizer & Préparation des batches (masquage tours utilisateur)
tok = AutoTokenizer.from_pretrained(model_path, padding_side="right")
if tok.pad_token_id is None:
    tok.pad_token_id = tok.eos_token_id

# Longueur de contexte calibrée à 2048 tokens pour garantir une marge de VRAM totale
MAX_LEN = 2048

def tokenize(ex, tokenizer, max_len=MAX_LEN):
    msgs = ex["messages"]
    # Gemma n'a pas de rôle system natif : fusion dans le premier tour user
    if msgs[0]["role"] == "system":
        msgs = [{"role": "user", "content": msgs[0]["content"] + "\n\n" + msgs[1]["content"]}] + msgs[2:]
    ids, labels = [], []
    prev_ids = []
    for i in range(len(msgs)):
        cur_text = tokenizer.apply_chat_template(msgs[: i + 1], tokenize=False, add_generation_prompt=False)
        encoded = tokenizer.encode(cur_text, add_special_tokens=False)
        if hasattr(encoded, "ids"):
            cur = [int(x) for x in encoded.ids]
        else:
            cur = [int(x) for x in encoded]

        new = cur[len(prev_ids):]
        ids.extend(new)
        if msgs[i]["role"] == "assistant":
            labels.extend(new)
        else:
            labels.extend([-100] * len(new))
        prev_ids = cur

    if len(ids) > max_len:
        return {"input_ids": None, "labels": None}
    return {"input_ids": ids, "labels": labels}


def collate(batch, pad):
    n = max(len(b["input_ids"]) for b in batch)
    ids = torch.full((len(batch), n), pad, dtype=torch.long)
    lab = torch.full((len(batch), n), -100, dtype=torch.long)
    att = torch.zeros((len(batch), n), dtype=torch.long)
    for i, b in enumerate(batch):
        k = len(b["input_ids"])
        ids[i, :k] = torch.tensor(b["input_ids"], dtype=torch.long)
        lab[i, :k] = torch.tensor(b["labels"], dtype=torch.long)
        att[i, :k] = 1
    return {"input_ids": ids, "labels": lab, "attention_mask": att}


ds = Dataset.from_list([{"messages": r["messages"]} for r in filtered])
ds = ds.map(lambda e: tokenize(e, tok, max_len=MAX_LEN), remove_columns=["messages"])
ds = ds.filter(lambda e: e["input_ids"] is not None)
print(f"✅ Exemples exploitables après tokenisation (≤ {MAX_LEN} tokens) : {len(ds)}")

# 5. Configuration QLoRA 4-bit (NF4, bfloat16 si supporté) et répartition équilibrée (balanced)
compute_dtype = torch.bfloat16 if torch.cuda.is_bf16_supported() else torch.float16
bnb_config = BitsAndBytesConfig(
    load_in_4bit=True,
    bnb_4bit_quant_type="nf4",
    bnb_4bit_use_double_quant=True,
    bnb_4bit_compute_dtype=compute_dtype
)

# Répartition équilibrée (balanced) sur tous les GPU disponibles
d_map = "balanced" if num_gpus > 1 else "auto"
print(f"Stratégie de placement sur GPU : {d_map}")

model = AutoModelForCausalLM.from_pretrained(
    model_path,
    quantization_config=bnb_config,
    device_map=d_map,
    torch_dtype=compute_dtype,
    attn_implementation="eager"
)

# Désactivation du final_logit_softcapping pour éliminer l'allocation dupliquée de logits
for obj in [model, getattr(model, "model", None), getattr(model, "config", None), getattr(getattr(model, "config", None), "text_config", None)]:
    if obj is not None and hasattr(obj, "final_logit_softcapping"):
        obj.final_logit_softcapping = None
    if obj is not None and hasattr(obj, "config") and hasattr(obj.config, "final_logit_softcapping"):
        obj.config.final_logit_softcapping = None

# Gel des paramètres du modèle de base
for param in model.parameters():
    param.requires_grad = False

if hasattr(model, "enable_input_require_grads"):
    model.enable_input_require_grads()
else:
    def make_inputs_require_grad(module, input, output):
        output.requires_grad_(True)
    if hasattr(model, "get_input_embeddings"):
        emb = model.get_input_embeddings()
        if emb is not None:
            emb.register_forward_hook(make_inputs_require_grad)

if hasattr(model, "gradient_checkpointing_enable"):
    model.gradient_checkpointing_enable()

# Sélection ciblée : UNIQUEMENT les couches linéaires du langage textuel (exclut vision_tower et audio_tower)
target_names = []
for name, module in model.named_modules():
    if "language_model" in name:
        if any(name.endswith(k) for k in ["q_proj", "k_proj", "v_proj", "o_proj", "gate_proj", "up_proj", "down_proj"]):
            target_names.append(name)

print(f"Couches LoRA cibles sélectionnées dans language_model : {len(target_names)}")

peft_config = LoraConfig(
    r=16,
    lora_alpha=32,
    target_modules=target_names,
    lora_dropout=0.05,
    bias="none",
    task_type="CAUSAL_LM"
)
model = get_peft_model(model, peft_config)
model.print_trainable_parameters()

# 6. Configuration et Entraînement
OUT_DIR = Path("/kaggle/working/lora_gemma4")
training_args = TrainingArguments(
    output_dir=str(OUT_DIR),
    num_train_epochs=3,
    learning_rate=5e-5,
    lr_scheduler_type="cosine",
    warmup_steps=10,
    max_grad_norm=0.1,  # Stabilité QK-RMSNorm Gemma 4
    per_device_train_batch_size=1,
    gradient_accumulation_steps=8,
    gradient_checkpointing=True,
    logging_steps=5,
    save_strategy="epoch",
    bf16=torch.cuda.is_bf16_supported(),
    fp16=not torch.cuda.is_bf16_supported(),
    optim="paged_adamw_8bit",
    report_to="none",
    seed=42
)

def get_lm_head(m):
    if hasattr(m, "lm_head"):
        return m.lm_head
    if hasattr(m, "base_model"):
        return get_lm_head(m.base_model)
    if hasattr(m, "model"):
        return get_lm_head(m.model)
    raise AttributeError("Impossible de localiser lm_head dans le modèle")

class ChunkedLossTrainer(Trainer):
    """Calcule la perte Cross-Entropy par micro-chunks de 256 tokens sur les tours assistant.
    Évite l'allocation explosive d'un tenseur de logits 4 Go (vocab 262k x seq 2048).
    Réduit le pic VRAM de la perte de plus de 90%.
    """
    def compute_loss(self, model, inputs, return_outputs=False, **kwargs):
        labels = inputs.get("labels")
        # Exclut labels pour empêcher le calcul interne de logits float32 non chunké
        fwd_inputs = {k: v for k, v in inputs.items() if k != "labels"}
        try:
            outputs = model(**fwd_inputs, logits_to_keep=1, output_hidden_states=True)
        except TypeError:
            outputs = model(**fwd_inputs, output_hidden_states=True)

        # Récupération de l'état caché final (garanti par output_hidden_states=True)
        if hasattr(outputs, "hidden_states") and outputs.hidden_states is not None:
            hidden = outputs.hidden_states[-1]
        elif hasattr(outputs, "last_hidden_state") and outputs.last_hidden_state is not None:
            hidden = outputs.last_hidden_state
        else:
            raise RuntimeError(f"Impossible d'extraire les états cachés : champs={dir(outputs)}")

        # Décalage causal standard (le token t prédit t+1)
        shift_h = hidden[:, :-1, :].contiguous()
        shift_l = labels[:, 1:].contiguous()

        # Filtrage strict des tokens assistant (masquage user / padding / prompt)
        mask = (shift_l != -100)
        if not mask.any():
            dummy = torch.tensor(0.0, device=shift_h.device, requires_grad=True)
            return (dummy, outputs) if return_outputs else dummy

        active_h = shift_h[mask]
        active_l = shift_l[mask]

        head = get_lm_head(model)
        head_param = next(head.parameters())
        head_dev = head_param.device
        head_dtype = head_param.dtype
        total_loss = 0.0
        chunk_size = 256

        for i in range(0, active_h.size(0), chunk_size):
            h_chunk = active_h[i : i + chunk_size].to(device=head_dev, dtype=head_dtype)
            l_chunk = active_l[i : i + chunk_size].to(device=head_dev)
            logits_chunk = head(h_chunk).float()
            chunk_loss = torch.nn.functional.cross_entropy(logits_chunk, l_chunk, reduction="sum")
            total_loss = total_loss + chunk_loss

        loss = (total_loss / active_l.numel()).to(shift_h.device)
        return (loss, outputs) if return_outputs else loss

trainer = ChunkedLossTrainer(
    model=model,
    args=training_args,
    train_dataset=ds,
    data_collator=lambda b: collate(b, tok.pad_token_id or 0)
)

print("=== LANCEMENT DU FINE-TUNING QLoRA (3 ÉPOQUES AVEC CHUNKED LOSS) ===")
trainer.train()

# 7. Sauvegarde de l'adaptateur LoRA et création du ZIP
final_dir = OUT_DIR / "final"
final_dir.mkdir(parents=True, exist_ok=True)
model.save_pretrained(str(final_dir))
tok.save_pretrained(str(final_dir))

zip_base = Path("/kaggle/working/gemma4_lora_final")
shutil.make_archive(str(zip_base), "zip", str(final_dir))
print(f"🎉 SUCCÈS TOTAL : Adaptateur LoRA archivé dans {zip_base}.zip prêt pour téléchargement !")
