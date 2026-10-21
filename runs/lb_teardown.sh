#!/usr/bin/env bash
# Attend la fin du passage <tag> sur la session Colab <s>, sauvegarde les résultats sur le serveur, puis libère l'A100.
S=$1; TAG=$2; OUT=/home/elrems/kaggle/runs/leaderboard_local/$TAG
until /home/elrems/kaggle/tools/colab_tail.sh "$S" "/content/lb/run_$TAG.log" 3 2>/dev/null | grep -q "PASSAGE TERMINÉ"; do sleep 300; done
mkdir -p "$OUT"
printf 'import subprocess\nsubprocess.run("cd /content/lb/runs && tar czf /content/lb/res_%s.tgz %s nuit1", shell=True)\nprint("ok")\n' "$TAG" "$TAG" > /tmp/lbtd_$$.py
/home/elrems/.local/bin/colab4 exec -s "$S" -f /tmp/lbtd_$$.py
/home/elrems/.local/bin/colab4 download -s "$S" "/content/lb/res_$TAG.tgz" "$OUT/res.tgz" && tar xzf "$OUT/res.tgz" -C "$OUT"
/home/elrems/.local/bin/colab4 stop -s "$S" && echo "$(date -u +%FT%T) session Colab $S libérée"
