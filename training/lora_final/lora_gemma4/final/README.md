---
license: apache-2.0
base_model: google/gemma-4-E4B-it
library_name: peft
tags:
- gemma4
- qlora
- code-repair
- php
- prestashop
---

# lora_gemma4-e4b-prestashop

QLoRA adapter for **Gemma 4 E4B** (`google/gemma-4-E4B-it`) trained to follow a fixed bug-fixing flow on PrestaShop (PHP):
localise (keywords) → read (files) → edit (SEARCH/REPLACE blocks). Part of the Kaggle *Gemma 4 Developer Agent* Paper Track
submission — paper and code: https://github.com/ba-rem26007/gemma4-legacy-replay-public (`docs/KAGGLE_FINAL_WRITEUP.md`).

## Training data

585 prepared examples, all from PrestaShop bugs merged **before 2025-06-01** (Gemma 4's declared cutoff is January 2025):
- 569 paths reconstructed deterministically from official upstream fixes (`trajectories/reconstruct.py`);
- 16 paths produced by Gemma 4 31B itself and verified by execution (`trajectories/self_paths.py`).

Examples touching any function also fixed after the split were excluded. No proprietary-model output is included.
With `MAX_LEN = 2048`, **89 examples** were actually used. Exact snapshot: `training/snapshots/trajectories_v15.tar.gz`.

## Training

4-bit QLoRA, r = 16, α = 32, 3 epochs (18 steps), lr 5e-5, 2× T4 on Kaggle, 7,209 s, mean training loss 1.192
(`training/snapshots/train_kaggle_v15.py`, log `training/lora_final/gemma-4-qlora-training-prestashop.log`).
A chunked cross-entropy (`training/chunked_loss.py`) avoids materialising the full logits tensor over the 262k vocabulary.
`adapter_model.safetensors` sha256 starts with `fac3f1af8b0fb855`.

## Evaluation (33 hidden-oracle TEST bugs, PrestaShop 9.1.x, merged after the split)

Condition E (rules + glossary + replay spec, 2 retries), **3 runs per model on the same infrastructure**
(base loaded in 4-bit NF4 as in training; `docs/RESULTATS_E4B.md`, recomputed by `notebook/verification.ipynb`):

| Model | Solved per run | Mean | Right file read | Δ vs base (95% CI) |
|---|---|---|---|---|
| Gemma 4 E4B base (no adapter) | 3, 4, 4 | 3.67 / 33 | 13–16 | — |
| **E4B + this adapter (v15, 89 examples)** | 1, 1, 2 | 1.33 / 33 | 11–13 | −7.1 pts [−16.2, 0.0] |
| E4B + v16 adapter (453 examples, ≤ 4096 tokens) | 0, 0, 0 | 0 / 33 | 11 | −11.1 pts [−23.2, −2.0] |

**This adapter does not improve Gemma 4 E4B on this benchmark; the larger v16 adapter degrades it.** Patch application
rates are unchanged; localisation drops. An earlier single-run figure (4/33, 28 Sept.) is within the base model's noise.
We publish the adapter for reproducibility of this negative result.

## Limitations

Small training set (89 examples), n = 33 test bugs, single run for E. PrestaShop is © PrestaShop SA and contributors (OSL-3.0).
