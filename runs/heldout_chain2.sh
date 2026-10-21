#!/usr/bin/env bash
# Validation TRAIN : reprend le run N interrompu (bugs sans résultat) puis les runs suivants jusqu'au 3e. Unité systemd --user.
# Usage : runs/heldout_chain2.sh <port_local> <modèle> <PSB> <dossier_run_N_interrompu> <N>
cd /home/elrems/kaggle
PORT=$1; MODEL=$2; export PSB=$3; PREV=$4; N=$5
export LLM_BASE_URL="http://localhost:$PORT/v1" TPM_LIMIT=1000000 PYTHONUNBUFFERED=1 ORACLE_PREFIX=g
ALL=$(python3 -c "import json;print(' '.join(map(str,json.load(open('data/heldout_train_v17.json'))['bugs'])))")
until curl -s -m 5 "localhost:$PORT/" | grep -q ready; do sleep 30; done
TODO=$(for b in $ALL; do [ -f "$PREV/$b/result.json" ] || echo $b; done | tr '\n' ' ')
echo "=== $MODEL run $N (reprise de $PREV) $(date -u +%FT%T) : $TODO ==="
[ -n "$TODO" ] && python3 -u agent/run.py --bugs $TODO --condition E --model "$MODEL" --retries 2
for r in $(seq $((N + 1)) 3); do echo "=== $MODEL run $r $(date -u +%FT%T) ==="; python3 -u agent/run.py --bugs $ALL --condition E --model "$MODEL" --retries 2; done
echo "=== FIN $MODEL $(date -u +%FT%T) ==="
