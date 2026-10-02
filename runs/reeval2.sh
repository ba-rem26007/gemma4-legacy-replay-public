#!/usr/bin/env bash
# 2e réévaluation A/R/A-26B après correction de checkout.sh (fichiers d'agent hors correctif officiel restaurés).
# Usage : runs/reeval2.sh <PSB> "<dirs essai 1>" ["<dirs essai 2>" …] — instance remise à neuf avant chaque essai.
cd /home/elrems/kaggle
N=$1; shift; PROJ="psbench$([ "$N" = 1 ] || echo "$N")"
for trial in "$@"; do
  docker compose -p "$PROJ" -f bench/env/docker-compose.yml down -v >/dev/null 2>&1
  rm -f "bench/env/.state-$PROJ" "bench/env/.snap-$PROJ.sql.gz"
  for pass in 1 2; do PSB=$N REEVAL_OUT=result_reeval2.json python3 -u bench/reeval.py $trial; done
done
echo "FIN reeval2 instance $N"
