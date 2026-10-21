#!/usr/bin/env bash
# Levier L3 (DECISIONS.md, 2 oct.) : MAX_LINES_PER_FILE fixé, 2 répétitions, condition A, 31B, lot TRAIN data/train_l3_lecture.json.
# Usage : runs/l3_lecture.sh <MAX_LINES> <PSB>   (unité systemd --user)
cd /home/elrems/kaggle
export MAX_LINES_PER_FILE=$1 PSB=$2 ORACLE_PREFIX=g PYTHONUNBUFFERED=1
BUGS=$(python3 -c "import json;print(' '.join(map(str,json.load(open('data/train_l3_lecture.json'))['bugs'])))")
for r in 1 2; do echo "=== L3 lecture $1, répétition $r, $(date -u +%FT%T) ==="; python3 -u agent/run.py --bugs $BUGS --condition A --model gemma-4-31b-it --retries 2; done
echo "=== FIN L3 lecture $1 $(date -u +%FT%T) ==="
