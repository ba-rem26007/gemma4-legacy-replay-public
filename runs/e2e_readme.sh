#!/usr/bin/env bash
# Test de bout en bout des commandes du README public (instance PSB=3).
cd "$(dirname "$0")/.."; export PSB=3
echo "== pre (doit échouer)"; bench/checkout.sh 41007 pre >/dev/null 2>&1; bench/replay/run.sh 41007; echo "rc_pre=$?"
echo "== post (doit passer)"; bench/checkout.sh 41007 post >/dev/null 2>&1; bench/replay/run.sh 41007; echo "rc_post=$?"
echo "== agent A"; python3 agent/run.py --bugs 41007 --condition A 2>&1 | tail -3
R=$(ls -dt runs/*-A | head -1); echo "run=$R"
echo "== eval"; python3 bench/eval.py 41007 "$R/41007/patch.diff" 2>&1 | grep -E "\"(applied|fixed|regression)\""
echo FIN
