#!/usr/bin/env python3
"""Mesure la mémoire GPU de pointe de Gemma 4 E4B (4 bits NF4, comme tools/colab_llm_server.py) à la plus longue requête
observée en condition E (prompt de --prompt-tokens tokens, --new-tokens tokens générés). Usage (VM) :
python3 e4b_vram_probe.py --dtype fp16 --prompt-tokens 11299 --new-tokens 1024"""
import argparse, json, time
import torch, kagglehub
from transformers import AutoModelForCausalLM, AutoTokenizer, BitsAndBytesConfig
ap = argparse.ArgumentParser(); ap.add_argument("--dtype", default="fp16"); ap.add_argument("--prompt-tokens", type=int, default=11299)
ap.add_argument("--new-tokens", type=int, default=1024); a = ap.parse_args()
DT = torch.float16 if a.dtype == "fp16" else torch.bfloat16
base = kagglehub.model_download("google/gemma-4/transformers/gemma-4-e4b-it")
tok = AutoTokenizer.from_pretrained(base)
m = AutoModelForCausalLM.from_pretrained(base, device_map={"": 0}, dtype=DT, quantization_config=BitsAndBytesConfig(
    load_in_4bit=True, bnb_4bit_quant_type="nf4", bnb_4bit_use_double_quant=True, bnb_4bit_compute_dtype=DT)).eval()
load = torch.cuda.max_memory_allocated() / 2**30
filler = open(__file__).read() * 400
ids = tok(filler, return_tensors="pt").input_ids[:, :a.prompt_tokens].to(0)
torch.cuda.reset_peak_memory_stats(); t0 = time.time()
with torch.no_grad():
    out = m.generate(ids, max_new_tokens=a.new_tokens, min_new_tokens=a.new_tokens, do_sample=False)
dt = time.time() - t0
print(json.dumps({"gpu": torch.cuda.get_device_name(0), "dtype": a.dtype, "load_vram_gb": round(load, 2),
                  "peak_vram_gb": round(torch.cuda.max_memory_allocated() / 2**30, 2),
                  "reserved_gb": round(torch.cuda.max_memory_reserved() / 2**30, 2),
                  "prompt_tokens": ids.shape[1], "new_tokens": out.shape[1] - ids.shape[1], "seconds": round(dt, 1),
                  "decode_tok_s": round((out.shape[1] - ids.shape[1]) / dt, 1)}))
