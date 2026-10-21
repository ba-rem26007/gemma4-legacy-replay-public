#!/usr/bin/env bash
# 2 passages V4 contre v2, réglages identiques (time-scale 1.0), à la vitesse de l'évaluateur (slow_proxy).
cd /content/lb && tar xzf v4.tgz
bash colab_run.sh v4r1 base=submission=1.0 v4=submission_v4=1.0
bash colab_run.sh v4r2 base=submission=1.0 v4=submission_v4=1.0
echo "V4 TERMINÉ $(date -u +%FT%T)"
