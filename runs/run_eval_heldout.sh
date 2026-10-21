#!/usr/bin/env bash
# Validation TRAIN (lot mis de côté, data/heldout_train_v17.json) : condition E, Gemma 4 E4B servi sur Colab (tools/colab_llm_server.py)
# via tunnel ; verdict = oracle écrit par Gemma (bench/replay/g<pr>/, ORACLE_PREFIX=g). 3 runs à la suite.
# Usage : runs/run_eval_heldout.sh <port_local 1800N> <nom_modèle> <PSB>
set -e
cd "$(dirname "$0")/.."
PORT=$1; MODEL=$2; export PSB=$3
export LLM_BASE_URL="http://localhost:$PORT/v1" TPM_LIMIT=1000000 PYTHONUNBUFFERED=1 ORACLE_PREFIX=g
BUGS=$(python3 -c "import json;print(' '.join(map(str,json.load(open('data/heldout_train_v17.json'))['bugs'])))")
for r in 1 2 3; do
  echo "=== $MODEL run $r sur psbench$PSB (lot TRAIN mis de côté, 30 bugs), $(date -u +%FT%T) ==="
  python3 -u agent/run.py --bugs $BUGS --condition E --model "$MODEL" --retries 2
done
echo "=== FIN $MODEL $(date -u +%FT%T) ==="
