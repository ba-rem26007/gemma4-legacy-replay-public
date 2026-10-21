#!/usr/bin/env bash
# Attend « V4 TERMINÉ » (garde-fou 7 h), rapatrie v4r1/v4r2 + rejeux, libère l'A100.
OUT=/home/elrems/kaggle/runs/leaderboard_local; T0=$(date +%s)
until /home/elrems/kaggle/tools/colab_tail.sh lb /content/lb/v4.log 3 2>/dev/null | grep -q "V4 TERMINÉ"; do
  [ $(( $(date +%s) - T0 )) -gt 25200 ] && { echo "garde-fou 7 h"; break; }; sleep 300; done
printf 'import subprocess\nsubprocess.run("cd /content/lb && tar czf res_v4.tgz runs/v4r1 runs/v4r2 runs/lent2 replay_*.jsonl replay_*.txt edits_lent2.jsonl slow_proxy_*.jsonl v4.log 2>/dev/null", shell=True)\nprint("ok")\n' > /tmp/lbv4_$$.py
/home/elrems/.local/bin/colab4 exec -s lb -f /tmp/lbv4_$$.py
mkdir -p $OUT/v4 && /home/elrems/.local/bin/colab4 download -s lb /content/lb/res_v4.tgz $OUT/v4/res.tgz && tar xzf $OUT/v4/res.tgz -C $OUT/v4
/home/elrems/.local/bin/colab4 stop -s lb && echo "$(date -u +%FT%T) session Colab lb libérée"
