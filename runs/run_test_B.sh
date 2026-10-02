#!/usr/bin/env bash
# Exécution autonome de l'évaluation benchmark Condition B sur les 33 bugs TEST
set -e
cd /home/elrems/kaggle
export PSB=2
export PYTHONUNBUFFERED=1

LOG="runs/test_B_current.log"
echo "=== Démarrage Benchmark Condition B sur psbench2 (33 bugs) : $(date -u) ===" >> "$LOG"

python3 -u agent/run.py --bugs $(cat runs/test_all.txt) --condition B --model gemma-4-31b-it --retries 2 >> "$LOG" 2>&1

echo "=== Fin Benchmark Condition B : $(date -u) ===" >> "$LOG"
