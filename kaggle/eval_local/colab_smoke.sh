#!/usr/bin/env bash
# Test de plomberie : faux modèle du kit (g4kit-fake-llm) sur 1 tâche, pour chaque soumission. Journal : /content/lb/smoke.log
cd /content/lb
pkill -INT -f g4kit-fake-llm 2>/dev/null; sleep 1
nohup g4kit-fake-llm --port 11450 > fake.log 2>&1 &
sleep 5
ID=$(python3 -c "import json;print(json.load(open('lot.json'))[0]['instance_id'])")
timeout 1200 g4kit-harness run --arm base=submission --arm v3=submission_v3 --tasks comp/tasks.jsonl --snapshots comp/snapshots \
  --ids "$ID" --api-base http://127.0.0.1:11450/v1 --out runs/smoke --sandbox subprocess \
  --wheels-dir comp/wheels --task-env /content/lb/taskenv --graph-dir comp/graphs --embeddings-dir comp/embeddings
echo "EXIT $?"
pkill -INT -f g4kit-fake-llm; sleep 3; tail -20 fake.log
echo "SMOKE TERMINÉ"
