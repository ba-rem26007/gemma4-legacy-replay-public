#!/usr/bin/env bash
# Lance la validation TRAIN (base / v16 / v17 × 3 runs) puis LIBÈRE la session Colab à la fin (leçon du 2 oct. : A100 à vide la nuit).
cd "$(dirname "$0")/.."
tools/colab_keepalive.sh v17 > /dev/null 2>&1 & KA=$!
runs/run_eval_heldout.sh 18001 gemma-4-e4b-base 1 > runs/heldout_base.log 2>&1 & P1=$!
sleep 3; runs/run_eval_heldout.sh 18002 gemma-4-e4b-lora-v16 6 > runs/heldout_v16.log 2>&1 & P2=$!
sleep 3; runs/run_eval_heldout.sh 18003 gemma-4-e4b-lora-v17 7 > runs/heldout_v17.log 2>&1 & P3=$!
echo "$(date -u +%FT%T) lancés : $P1 $P2 $P3 (maintien en vie $KA)"
wait $P1 $P2 $P3
echo "$(date -u +%FT%T) validation terminée : arrêt du maintien en vie, du tunnel et de la session Colab"
kill $KA 2>/dev/null
for p in $(ps -eo pid,args | grep "[c]olab_tunnel.sh v17" | awk '{print $1}'); do kill $p; done
for p in $(ps -eo pid,args | grep "[s]sh -i /home/elrems/.ssh/colab_ed25519.*colab-v17" | awk '{print $1}'); do kill $p; done
"$HOME/.local/bin/colab4" stop -s v17
echo "$(date -u +%FT%T) session Colab libérée"
