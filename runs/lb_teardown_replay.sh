#!/usr/bin/env bash
# Attend la fin du rejeu sur la session Colab <s>, rapatrie les résultats, libère l'A100 (garde-fou : 6 h max).
S=$1; OUT=/home/elrems/kaggle/runs/leaderboard_local/lent2; T0=$(date +%s)
until /home/elrems/kaggle/tools/colab_tail.sh "$S" /content/lb/replay_lent2.txt 3 2>/dev/null | grep -q "REJEU TERMINÉ"; do
  [ $(( $(date +%s) - T0 )) -gt 21600 ] && { echo "garde-fou 6 h atteint"; break; }; sleep 300; done
mkdir -p "$OUT"
printf 'import subprocess\nsubprocess.run("cd /content/lb && tar czf res_lent2.tgz runs/lent2 edits_lent2.jsonl replay_edits_lent2.jsonl replay_lent2.txt slow_proxy_lent2.jsonl all.log 2>/dev/null", shell=True)\nprint("ok")\n' > /tmp/lbtr_$$.py
/home/elrems/.local/bin/colab4 exec -s "$S" -f /tmp/lbtr_$$.py
/home/elrems/.local/bin/colab4 download -s "$S" /content/lb/res_lent2.tgz "$OUT/res.tgz" && tar xzf "$OUT/res.tgz" -C "$OUT"
/home/elrems/.local/bin/colab4 stop -s "$S" && echo "$(date -u +%FT%T) session Colab $S libérée"
