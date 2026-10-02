#!/usr/bin/env python3
"""Fine-Tuning Gemma 4 QLoRA autonome sur Kaggle GPU (Tesla T4 15 Go).

v16 (1er oct. 2026) : MAX_LEN 2048 → 4096 (89 → ~350 exemples), 2 époques, chemins Gemma filtrés à similarité ≥ 0,4,
garde-fou de durée (arrêt + sauvegarde à 10 h 30, sessions Kaggle limitées à 12 h). v15 : 2048, 3 époques, 89 exemples.
"""

import os
os.environ["PYTORCH_CUDA_ALLOC_CONF"] = "expandable_segments:True"

import glob
import json
import random
import shutil
import subprocess
import sys
from pathlib import Path

# Même script sur Kaggle (run complet) et sur Google Colab (test préalable, cf. docs/FINETUNING_KAGGLE.md §0) :
#   DATA_DIR  dossier contenant train.jsonl + self.jsonl (défaut /kaggle/input)
#   WORK_DIR  sorties (défaut /kaggle/working)
#   SMOKE=1   test court : pré-test mémoire + 2 pas d'optimisation, puis arrêt (aucun adaptateur à garder)
DATA_DIR = Path(os.environ.get("DATA_DIR", "/kaggle/input"))
WORK_DIR = Path(os.environ.get("WORK_DIR", "/kaggle/working"))
SMOKE = os.environ.get("SMOKE") == "1"

# 1. Installation & Mise à jour des modules requis (transformers>=5.17 pour gemma4)
print("=== INSTALLATION DES MODULES COMPLÉMENTAIRES ===")
subprocess.check_call([
    sys.executable, "-m", "pip", "install", "-q", "--upgrade",
    "transformers>=5.17.0", "accelerate", "bitsandbytes>=0.45.0", "peft>=0.14.0", "datasets", "kagglehub>=0.4.0"
])

import torch
import torch.utils.checkpoint
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
for p in DATA_DIR.rglob("*.jsonl"):
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

# Chemins Gemma (boucle d'auto-apprentissage) : seuil de qualité = similarité au correctif officiel ≥ 0,4
MIN_SIM = 0.4
n0 = len(rows)
rows = [r for r in rows if r.get("source") != "gemma_self" or (r.get("similarite") or 0) >= MIN_SIM]
print(f"Chemins Gemma sous le seuil de similarité {MIN_SIM} écartés : {n0 - len(rows)}")

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

# v16 : 4096 tokens (médiane des chemins ≈ 3 900 tokens ; à 2048 seuls 89/585 passaient)
MAX_LEN = 4096
EPOCHS = 2
TIME_LIMIT_S = 10.5 * 3600  # marge sous la limite de 12 h d'une session Kaggle

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
# v16 : UN seul GPU. Le modèle 4 bits + activations à 4096 tokens tient sur un T4 (pic 12,2 Go au pré-test du kernel 18).
# « balanced » laissait tout sur le GPU0 ; un plafond max_memory l'envoyait sur le GPU1, puis le Trainer le déplaçait
# vers le GPU0 → « CUDA illegal memory access » (bitsandbytes). Un seul GPU = même configuration que le test Colab.
d_map = {"": 0}
max_mem = None
print(f"Stratégie de placement sur GPU : {d_map}")

model = AutoModelForCausalLM.from_pretrained(
    model_path,
    quantization_config=bnb_config,
    device_map=d_map,
    max_memory=max_mem,
    torch_dtype=compute_dtype,
    attn_implementation="eager"
)

# Désactivation du final_logit_softcapping pour éliminer l'allocation dupliquée de logits
import collections
print("Placement des modules :", dict(collections.Counter(str(v) for v in getattr(model, "hf_device_map", {}).values())))
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

# Hook sur la norme finale du décodeur texte : sa sortie = hidden_states[-1] de HF, sans garder les autres couches
_norms = [(n, m) for n, m in model.named_modules() if n.endswith("language_model.norm")] or \
         [(n, m) for n, m in model.named_modules() if n.endswith("model.norm")]
_last_hidden = {}
_norms[-1][1].register_forward_hook(lambda mod, inp, out: _last_hidden.__setitem__("h", out))
print(f"État caché final capturé sur : {_norms[-1][0]}")
model.print_trainable_parameters()

# 6. Configuration et Entraînement
OUT_DIR = WORK_DIR / "lora_gemma4"
training_args = TrainingArguments(
    output_dir=str(OUT_DIR),
    num_train_epochs=EPOCHS,
    max_steps=2 if SMOKE else -1,
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
        # v16 : plus de output_hidden_states=True (l'entraînement fp16 convertissait les 43 états cachés en fp32
        # → OOM) ; le dernier état caché (sortie de la norme finale) est capturé par un hook.
        outputs = model(**fwd_inputs, logits_to_keep=1)
        hidden = _last_hidden.pop("h")


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

        # v16 : chaque morceau est recalculé à la rétropropagation (checkpoint) → un seul tenseur de logits
        # (256 × 262k) vit en mémoire à la fois. En v15 (≤ 2048 tokens) tous les morceaux restaient en mémoire ;
        # à 4096 tokens cela dépassait les 14,5 Go d'une T4 (OOM au 1er pas, kernel v16 du 1er oct.).
        def chunk_ce(h, l):
            return torch.nn.functional.cross_entropy(head(h).float(), l, reduction="sum")

        for i in range(0, active_h.size(0), chunk_size):
            h_chunk = active_h[i : i + chunk_size].to(device=head_dev, dtype=head_dtype)
            l_chunk = active_l[i : i + chunk_size].to(device=head_dev)
            total_loss = total_loss + torch.utils.checkpoint.checkpoint(chunk_ce, h_chunk, l_chunk, use_reentrant=False)

        loss = (total_loss / active_l.numel()).to(shift_h.device)
        if not return_outputs:
            del outputs, hidden  # libère les états cachés intermédiaires avant la rétropropagation
        return (loss, outputs) if return_outputs else loss

trainer = ChunkedLossTrainer(
    model=model,
    args=training_args,
    train_dataset=ds,
    data_collator=lambda b: collate(b, tok.pad_token_id or 0)
)

import time
from transformers import TrainerCallback


class TimeLimit(TrainerCallback):
    """Arrête proprement l'entraînement avant la limite de session ; l'adaptateur est sauvegardé ensuite."""
    def __init__(self, limit):
        self.limit, self.t0 = limit, time.time()

    def on_step_end(self, args, state, control, **kw):
        if time.time() - self.t0 > self.limit:
            print(f"⏱️ Limite de {self.limit / 3600:.1f} h atteinte à l'étape {state.global_step}/{state.max_steps} : arrêt et sauvegarde.")
            control.should_training_stop = True
        return control


# Pré-test mémoire : un pas complet (avant + arrière) sur l'exemple le plus long ; en cas d'OOM on abaisse MAX_LEN
# au lieu de perdre la session (v16 du 1er oct. : OOM au 1er pas).
def preflight(max_len):
    longest = max((x for x in ds if len(x["input_ids"]) <= max_len), key=lambda x: len(x["input_ids"]))
    batch = {k: v.to(model.device) for k, v in collate([longest], tok.pad_token_id or 0).items()}
    model.train()  # gradient checkpointing actif seulement en mode entraînement
    torch.cuda.reset_peak_memory_stats()
    with torch.autocast("cuda", dtype=compute_dtype):  # comme le Trainer en précision mixte
        loss = trainer.compute_loss(model, batch)
    loss.backward()
    model.zero_grad(set_to_none=True)
    peaks = [f"GPU{i} {torch.cuda.max_memory_allocated(i) / 2**30:.1f} Go" for i in range(num_gpus)]
    print(f"✅ Pré-test OK à {len(longest['input_ids'])} tokens : pic {', '.join(peaks)}")

for cap in (MAX_LEN, 3584, 3072, 2560, 2048):
    try:
        preflight(cap)
        if cap < MAX_LEN:
            ds = ds.filter(lambda e: len(e["input_ids"]) <= cap)
            trainer.train_dataset = ds
            print(f"⚠️ MAX_LEN abaissé à {cap} : {len(ds)} exemples")
            MAX_LEN = cap
        break
    except torch.OutOfMemoryError:
        model.zero_grad(set_to_none=True)
        torch.cuda.empty_cache()
        print(f"❌ OOM au pré-test à {cap} tokens")

trainer.add_callback(TimeLimit(TIME_LIMIT_S))
if SMOKE:
    print("=== MODE SMOKE : 2 pas seulement (test préalable hors Kaggle) ===")
lens = sorted(len(x) for x in ds["input_ids"])
print(f"Longueurs (tokens) : min {lens[0]}, médiane {lens[len(lens) // 2]}, max {lens[-1]}, total {sum(lens)}")
print(f"=== LANCEMENT DU FINE-TUNING QLoRA ({EPOCHS} ÉPOQUES, MAX_LEN {MAX_LEN}, CHUNKED LOSS) ===")
trainer.train()

# 7. Sauvegarde de l'adaptateur LoRA et création du ZIP
final_dir = OUT_DIR / "final"
final_dir.mkdir(parents=True, exist_ok=True)
model.save_pretrained(str(final_dir))
tok.save_pretrained(str(final_dir))

zip_base = WORK_DIR / "gemma4_lora_final"
shutil.make_archive(str(zip_base), "zip", str(final_dir))
print(f"🎉 SUCCÈS TOTAL : Adaptateur LoRA archivé dans {zip_base}.zip prêt pour téléchargement !")
