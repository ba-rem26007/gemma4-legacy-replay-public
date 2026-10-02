#!/usr/bin/env bash
# Exécution de l'évaluation benchmark Condition D (Gemma 4 Fine-Tuné LoRA) sur les 33 bugs TEST
set -e
cd /home/elrems/kaggle
export PSB=2
export PYTHONUNBUFFERED=1

NGROK_URL="${1:?usage: $0 <url_serveur_openai_compatible>}"
export LLM_BASE_URL="${NGROK_URL%/v1}/v1"
export LLM_MODEL="gemma-4-ft"
export TPM_LIMIT="1000000"

LOG="runs/test_D_current.log"
echo "=== Démarrage Benchmark Condition D sur psbench2 (33 bugs) : $(date -u) ===" >> "$LOG"
echo "LLM_BASE_URL: $LLM_BASE_URL | LLM_MODEL: $LLM_MODEL" >> "$LOG"

python3 -u agent/run.py --bugs $(cat runs/test_all.txt) --condition D --model gemma-4-ft --retries 2 >> "$LOG" 2>&1

echo "=== Fin Benchmark Condition D : $(date -u) ===" >> "$LOG"
