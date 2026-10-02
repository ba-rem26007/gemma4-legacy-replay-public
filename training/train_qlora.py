#!/usr/bin/env python3
"""Fine-tuning QLoRA de Gemma 4 sur les chemins condensés (trajectories/train.jsonl).

- Loss UNIQUEMENT sur les tours assistant (ticket, résultats d'outils, contenus de fichiers masqués).
- Checkpoints réguliers (sessions Kaggle de 12 h / coupures PC), reprise automatique.

PC (RTX 4070 Ti 12 Go) :
  python training/train_qlora.py --model google/gemma-4-e4b-it --max-len 4096
Kaggle (P100/T4 16 Go) :
  python training/train_qlora.py --model google/gemma-4-12b-it --max-len 4096 --out /kaggle/working/lora
"""
import argparse, json, os, random
from pathlib import Path

import torch
from datasets import Dataset
from peft import LoraConfig, get_peft_model, prepare_model_for_kbit_training
from transformers import (AutoModelForCausalLM, AutoTokenizer, BitsAndBytesConfig, Trainer,
                          TrainingArguments)
from transformers.trainer_utils import get_last_checkpoint

ROOT = Path(__file__).resolve().parent.parent


def tokenize(ex, tok, max_len):
    """input_ids du dialogue complet ; labels = -100 partout sauf sur les tours assistant."""
    msgs = ex["messages"]
    # Gemma n'a pas de rôle system : on le fusionne dans le 1er tour user
    if msgs[0]["role"] == "system":
        msgs = [{"role": "user", "content": msgs[0]["content"] + "\n\n" + msgs[1]["content"]}] + msgs[2:]
    ids, labels = [], []
    prev = []
    for i in range(len(msgs)):
        cur = tok.apply_chat_template(msgs[: i + 1], tokenize=True, add_generation_prompt=False)
        new = cur[len(prev):]
        ids += new
        labels += new if msgs[i]["role"] == "assistant" else [-100] * len(new)
        prev = cur
    if len(ids) > max_len:
        return {"input_ids": None, "labels": None}
    return {"input_ids": ids, "labels": labels}


def collate(batch, pad):
    n = max(len(b["input_ids"]) for b in batch)
    ids = torch.full((len(batch), n), pad)
    lab = torch.full((len(batch), n), -100)
    att = torch.zeros((len(batch), n), dtype=torch.long)
    for i, b in enumerate(batch):
        k = len(b["input_ids"])
        ids[i, :k] = torch.tensor(b["input_ids"]); lab[i, :k] = torch.tensor(b["labels"]); att[i, :k] = 1
    return {"input_ids": ids, "labels": lab, "attention_mask": att}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--model", default=os.environ.get("BASE_MODEL", "google/gemma-4-e4b-it"))
    # plusieurs fichiers séparés par des virgules : chemins reconstruits + chemins Gemma vérifiés (boucle d'auto-apprentissage)
    ap.add_argument("--data", default=",".join(str(ROOT / "trajectories" / f) for f in ("train.jsonl", "self.jsonl")))
    ap.add_argument("--out", default=str(ROOT / "training" / "lora"))
    # 8192 : cohérent avec la borne < 8 000 tokens des chemins ; à 4096, ~40 % des chemins reconstruits étaient ÉCARTÉS
    ap.add_argument("--max-len", type=int, default=8192)
    ap.add_argument("--epochs", type=float, default=2)
    ap.add_argument("--lr", type=float, default=5e-5, help="Gemma 4 : 5e-5 recommandé pour la stabilité")
    ap.add_argument("--max-grad-norm", type=float, default=0.1, help="Stabilité QK-RMSNorm Gemma 4 (seuil strict 0.1)")
    ap.add_argument("--rank", type=int, default=16)
    ap.add_argument("--grad-accum", type=int, default=8)
    ap.add_argument("--save-steps", type=int, default=25)
    ap.add_argument("--max-per-bug", type=int, default=2, help="kit : 2 chemins max par bug")
    ap.add_argument("--min-sim", type=float, default=0.4,
                    help="chemins Gemma (source=gemma_self) : similarité minimale au correctif officiel (0 = tous)")
    a = ap.parse_args()

    rows = [json.loads(l) for p in a.data.split(",") if Path(p).exists() for l in open(p)]
    n0 = len(rows)
    rows = [r for r in rows if r.get("source") != "gemma_self" or (r.get("similarite") or 0) >= a.min_sim]
    print(f"seuil de similarité {a.min_sim} : {n0 - len(rows)} chemins Gemma écartés")
    print("exemples :", {src: sum(r.get("source") == src for r in rows) for src in {r.get("source") for r in rows}})
    per = {}
    random.seed(0); random.shuffle(rows)
    rows = [r for r in rows if per.setdefault(r["pr"], []).append(1) or len(per[r["pr"]]) <= a.max_per_bug]
    print(f"{len(rows)} chemins ({sum(r['source'] == 'auto' for r in rows)} auto)")

    tok = AutoTokenizer.from_pretrained(a.model)
    ds = Dataset.from_list([{"messages": r["messages"]} for r in rows])
    ds = ds.map(lambda e: tokenize(e, tok, a.max_len), remove_columns=["messages"])
    ds = ds.filter(lambda e: e["input_ids"] is not None)
    print(f"{len(ds)} exemples ≤ {a.max_len} tokens ({len(rows) - len(ds)} écartés car trop longs)")

    bnb = BitsAndBytesConfig(load_in_4bit=True, bnb_4bit_quant_type="nf4", bnb_4bit_use_double_quant=True,
                             bnb_4bit_compute_dtype=torch.bfloat16 if torch.cuda.is_bf16_supported() else torch.float16)
    model = AutoModelForCausalLM.from_pretrained(a.model, quantization_config=bnb, device_map="auto",
                                                 torch_dtype=bnb.bnb_4bit_compute_dtype, attn_implementation="eager")
    model = prepare_model_for_kbit_training(model, use_gradient_checkpointing=True)
    model = get_peft_model(model, LoraConfig(r=a.rank, lora_alpha=2 * a.rank, lora_dropout=0.05, task_type="CAUSAL_LM",
                                             target_modules=["q_proj", "k_proj", "v_proj", "o_proj",
                                                             "gate_proj", "up_proj", "down_proj"]))
    model.print_trainable_parameters()

    args = TrainingArguments(
        output_dir=a.out, num_train_epochs=a.epochs, learning_rate=a.lr, lr_scheduler_type="cosine",
        warmup_ratio=0.05, max_grad_norm=a.max_grad_norm, per_device_train_batch_size=1, gradient_accumulation_steps=a.grad_accum,
        gradient_checkpointing=True, logging_steps=5, save_steps=a.save_steps, save_total_limit=3,
        bf16=torch.cuda.is_bf16_supported(), fp16=not torch.cuda.is_bf16_supported(),
        optim="paged_adamw_8bit", report_to="none", remove_unused_columns=False, seed=42)
    trainer = Trainer(model=model, args=args, train_dataset=ds,
                      data_collator=lambda b: collate(b, tok.pad_token_id or 0))
    last = get_last_checkpoint(a.out) if Path(a.out).exists() else None
    trainer.train(resume_from_checkpoint=last)
    model.save_pretrained(Path(a.out) / "final"); tok.save_pretrained(Path(a.out) / "final")
    json.dump(trainer.state.log_history, open(Path(a.out) / "log_history.json", "w"), indent=1)
    print(f"Adaptateur → {Path(a.out) / 'final'}")


if __name__ == "__main__":
    main()
