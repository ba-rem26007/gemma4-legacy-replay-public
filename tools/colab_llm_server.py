#!/usr/bin/env python3
"""Serveur d'inférence OpenAI-compatible (/v1/chat/completions) pour Gemma 4 E4B ± adaptateur LoRA, sur une VM Colab.

Lancé sur la VM par la CLI Colab ; joint depuis le serveur par un tunnel SSH privé (pas de ngrok, pas d'URL publique).
Base chargée comme à l'entraînement : 4 bits NF4 (QLoRA), calcul bf16. Un processus = un modèle = un port.
Usage (VM) : python3 colab_llm_server.py --port 8001 --name gemma-4-e4b-base [--dtype fp16 --stats /content/llm_stats.jsonl]
             python3 colab_llm_server.py --port 8003 --name gemma-4-e4b-lora-v16 --adapter /content/adapters/v16
Remplace la cellule ngrok de docs/CONDITION_D.md (même API, même format de réponse).
"""
import argparse, json, os, threading, time
from pathlib import Path

ap = argparse.ArgumentParser()
ap.add_argument("--port", type=int, required=True)
ap.add_argument("--name", required=True)
ap.add_argument("--adapter", default=None)
ap.add_argument("--dtype", default="bf16", choices=["bf16", "fp16"], help="fp16 sur T4 (pas de bf16 matériel)")
ap.add_argument("--stats", default=None, help="JSONL : une ligne par requête (tokens, secondes, VRAM de pointe)")
a = ap.parse_args()

import torch, uvicorn
from fastapi import FastAPI
from pydantic import BaseModel
from transformers import AutoModelForCausalLM, AutoTokenizer, BitsAndBytesConfig

DT = torch.bfloat16 if a.dtype == "bf16" else torch.float16
# même source de poids que l'entraînement (training/kaggle_kernel/train_kaggle.py) : Kaggle Models
import kagglehub
base = kagglehub.model_download("google/gemma-4/transformers/gemma-4-e4b-it")
tok = AutoTokenizer.from_pretrained(base)
model = AutoModelForCausalLM.from_pretrained(
    base, device_map={"": 0}, dtype=DT,
    quantization_config=BitsAndBytesConfig(load_in_4bit=True, bnb_4bit_quant_type="nf4",
                                           bnb_4bit_use_double_quant=True, bnb_4bit_compute_dtype=DT))
if a.adapter:
    from peft import PeftModel
    assert (Path(a.adapter) / "adapter_model.safetensors").exists(), a.adapter
    model = PeftModel.from_pretrained(model, a.adapter)
model.eval()
LOCK = threading.Lock()  # generate() n'est pas réentrant
LOAD_GB = torch.cuda.max_memory_allocated() / 2**30
print(f"PRÊT {a.name} port {a.port} adaptateur={a.adapter} dtype={a.dtype} GPU={torch.cuda.get_device_name(0)} "
      f"VRAM après chargement {LOAD_GB:.2f} Go", flush=True)

app = FastAPI()


class Msg(BaseModel):
    role: str
    content: str


class Req(BaseModel):
    model: str = ""
    messages: list[Msg]
    temperature: float = 0.2
    max_tokens: int = 4096


@app.get("/")
def root():
    return {"status": "ready", "name": a.name, "adapter": a.adapter}


@app.post("/v1/chat/completions")
def chat(req: Req):
    msgs = [{"role": m.role, "content": m.content} for m in req.messages]
    # même mise en forme qu'à l'entraînement (train_kaggle.py, tokenize) : système fusionné dans le 1er tour utilisateur
    if len(msgs) > 1 and msgs[0]["role"] == "system":
        msgs = [{"role": "user", "content": msgs[0]["content"] + "\n\n" + msgs[1]["content"]}] + msgs[2:]
    ids = tok.apply_chat_template(msgs, add_generation_prompt=True, return_tensors="pt", return_dict=True).to(model.device)
    n = ids["input_ids"].shape[1]
    with LOCK, torch.no_grad():
        t0 = time.time(); torch.cuda.reset_peak_memory_stats()
        out = model.generate(**ids, max_new_tokens=min(req.max_tokens, 4096), do_sample=req.temperature > 0.05,
                             temperature=max(req.temperature, 0.01), pad_token_id=tok.pad_token_id or tok.eos_token_id)
        dt = time.time() - t0; peak = torch.cuda.max_memory_allocated() / 2**30
    text = tok.decode(out[0][n:], skip_special_tokens=True).strip()
    if a.stats:
        with open(a.stats, "a") as f:
            f.write(json.dumps({"t": time.time(), "prompt_tokens": n, "completion_tokens": len(out[0]) - n, "seconds": round(dt, 2),
                                "peak_vram_gb": round(peak, 3), "load_vram_gb": round(LOAD_GB, 3),
                                "gpu": torch.cuda.get_device_name(0), "dtype": a.dtype}) + "\n")
    return {"id": f"chatcmpl-{time.time_ns()}", "object": "chat.completion", "created": int(time.time()), "model": a.name,
            "choices": [{"index": 0, "message": {"role": "assistant", "content": text}, "finish_reason": "stop"}],
            "usage": {"prompt_tokens": n, "completion_tokens": len(out[0]) - n, "total_tokens": len(out[0])}}


uvicorn.run(app, host="127.0.0.1", port=a.port, log_level="warning")
