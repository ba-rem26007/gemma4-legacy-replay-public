#!/usr/bin/env bash
# Boucle de nuit : chaque oracle Gemma VALIDÉ (bench/replay/g<pr>/STATUS = valide) sans essai de l'agent
# → agent Gemma condition O (4 corrections, déroulé v2) sur l'instance 4. S'arrête quand les lots gentest sont finis.
cd /home/elrems/kaggle
DONE=runs/loop_agent.done; touch $DONE
while true; do
  for s in bench/replay/g*/STATUS; do
    pr=$(basename "$(dirname "$s")"); pr=${pr#g}
    grep -q '^valide' "$s" || continue
    grep -qx "$pr" $DONE && continue
    echo "$pr" >> $DONE
    echo "== $(date -u +%H:%M) agent O sur #$pr"
    ORACLE_PREFIX=g PSB=4 python3 -u agent/run.py --bugs "$pr" --condition O --model gemma-4-31b-it --retries 4 2>&1 | grep -E '^→|Traceback'
  done
  # fin : plus aucun lot gentest actif
  if ! pgrep -f "bench/gentest.py" >/dev/null && ! pgrep -f queue_lot3 >/dev/null && ! pgrep -f queue_lot4 >/dev/null; then
    python3 bench/loop_stats.py > /dev/null
    echo "FIN boucle agent $(date -u +%H:%M)"; break
  fi
  sleep 300
done
