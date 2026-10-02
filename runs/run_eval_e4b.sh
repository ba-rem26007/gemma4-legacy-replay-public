#!/usr/bin/env bash
# Condition E (règles + glossaire + spec de rejeu, 2 reprises) avec Gemma 4 E4B servi sur Colab A100 (tools/colab_llm_server.py)
# via le tunnel privé tools/colab_tunnel.sh. Usage : runs/run_eval_e4b.sh <port_local 1800N> <nom_modèle> <PSB> <bug…>
set -e
cd "$(dirname "$0")/.."
PORT=$1; MODEL=$2; export PSB=$3; shift 3
export LLM_BASE_URL="http://localhost:$PORT/v1" TPM_LIMIT=1000000 PYTHONUNBUFFERED=1
echo "=== $MODEL sur psbench$PSB, $# bugs, $(date -u +%FT%T) ==="
python3 -u agent/run.py --bugs "$@" --condition E --model "$MODEL" --retries 2
echo "=== FIN $MODEL $(date -u +%FT%T) ==="
