#!/usr/bin/env bash
# Validation TRAIN d'un modèle : reprise du run 1 (bugs sans résultat) puis runs 2 et 3. Lancé en unité systemd --user.
# Usage : runs/heldout_chain.sh <port_local> <modèle> <PSB> <dossier_run1_interrompu>
cd /home/elrems/kaggle
PORT=$1; MODEL=$2; export PSB=$3; PREV=$4
export LLM_BASE_URL="http://localhost:$PORT/v1" TPM_LIMIT=1000000 PYTHONUNBUFFERED=1 ORACLE_PREFIX=g
ALL=$(python3 -c "import json;print(' '.join(map(str,json.load(open('data/heldout_train_v17.json'))['bugs'])))")
until curl -s -m 5 "localhost:$PORT/" | grep -q ready; do echo "$(date -u +%T) attente du tunnel…"; sleep 30; done
TODO=$(for b in $ALL; do [ -f "$PREV/$b/result.json" ] || echo $b; done | tr '\n' ' ')
echo "=== $MODEL run 1 (reprise de $PREV) : $TODO ==="
[ -n "$TODO" ] && python3 -u agent/run.py --bugs $TODO --condition E --model "$MODEL" --retries 2
for r in 2 3; do
  echo "=== $MODEL run $r $(date -u +%FT%T) ==="
  python3 -u agent/run.py --bugs $ALL --condition E --model "$MODEL" --retries 2
done
echo "=== FIN $MODEL $(date -u +%FT%T) ==="
