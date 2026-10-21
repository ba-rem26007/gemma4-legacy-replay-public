#!/usr/bin/env bash
# (version avec session) Attend « PASSAGE TERMINÉ <tag> » dans all.log (garde-fou <heures>), rapatrie runs/<tag> + journaux, libère l'A100.
TAG=$1; H=${2:-7}; S=${3:-lb}; OUT=/home/elrems/kaggle/runs/leaderboard_local/$TAG; T0=$(date +%s)
until /home/elrems/kaggle/tools/colab_tail.sh "$S" /content/lb/all.log 3 2>/dev/null | grep -q "PASSAGE TERMINÉ $TAG"; do
  [ $(( $(date +%s) - T0 )) -gt $((H * 3600)) ] && { echo "garde-fou ${H} h"; break; }; sleep 300; done
printf 'import subprocess\nsubprocess.run("cd /content/lb && tar czf res_%s.tgz runs/%s slow_proxy_%s.jsonl runs_%s_*.log all.log 2>/dev/null", shell=True)\nprint("ok")\n' "$TAG" "$TAG" "$TAG" "$TAG" > /tmp/lbt_$$.py
/home/elrems/.local/bin/colab4 exec -s "$S" -f /tmp/lbt_$$.py
mkdir -p "$OUT" && /home/elrems/.local/bin/colab4 download -s "$S" "/content/lb/res_$TAG.tgz" "$OUT/res.tgz" && tar xzf "$OUT/res.tgz" -C "$OUT"
/home/elrems/.local/bin/colab4 stop -s "$S" && echo "$(date -u +%FT%T) session Colab $S libérée"
