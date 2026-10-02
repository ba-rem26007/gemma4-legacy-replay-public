#!/usr/bin/env bash
# Lot 3 d'oracles PHP : démarre sur chaque instance (3 puis 1) dès que son lot courant est fini ; moitié chacun.
cd /home/elrems/kaggle
read -ra ALL < runs/lot3.txt; H=$(( ${#ALL[@]} / 2 ))
run_after() { # $1 = motif du lot courant, $2 = instance, reste = bugs
  local pat=$1 psb=$2; shift 2
  while pgrep -f "$pat" >/dev/null; do sleep 120; done
  PSB=$psb python3 -u bench/gentest.py "$@" --tries 4 --mode php >> runs/gentest_php3.log 2>&1
}
run_after 'gentest.py 38381' 3 "${ALL[@]:0:$H}" &
run_after 'gentest.py 36374' 1 "${ALL[@]:$H}" &
wait
