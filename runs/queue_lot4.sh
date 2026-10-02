#!/usr/bin/env bash
# Lot 4 (~220 bugs TRAIN côté serveur) : moitié B sur l'instance 1 tout de suite ; moitié A sur l'instance 3 quand le lot 3 y est fini.
cd /home/elrems/kaggle
read -ra ALL < runs/lot4.txt; H=$(( ${#ALL[@]} / 2 ))
PSB=1 python3 -u bench/gentest.py "${ALL[@]:$H}" --tries 4 --mode php >> runs/gentest_php4.log 2>&1 &
( while pgrep -f queue_lot3 >/dev/null; do sleep 300; done
  PSB=3 python3 -u bench/gentest.py "${ALL[@]:0:$H}" --tries 4 --mode php >> runs/gentest_php4.log 2>&1 ) &
wait
