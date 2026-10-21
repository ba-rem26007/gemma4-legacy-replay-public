#!/usr/bin/env bash
# Libère Colab v17 quand la validation est finie : processus base d'origine (PID donné) + unités kag-heldout-v16/v17.
BASE=$1
while kill -0 "$BASE" 2>/dev/null || systemctl --user is-active --quiet kag-heldout-v16 || systemctl --user is-active --quiet kag-heldout-v17; do sleep 120; done
echo "$(date -u +%FT%T) validation terminée, libération"
for p in $(ps -eo pid,args | grep -E "[c]olab_tunnel.sh v17|[c]olab_keepalive.sh v17" | awk '{print $1}'); do kill $p; done
systemctl --user stop kag-colab-keepalive kag-colab-tunnel 2>/dev/null
/home/elrems/.local/bin/colab4 stop -s v17 && echo "$(date -u +%FT%T) session Colab v17 libérée"
