#!/usr/bin/env bash
# Attend la fin des 3 unités de validation puis libère tunnel, maintien en vie et session Colab v17.
while systemctl --user is-active --quiet kag-heldout-base kag-heldout-v16 kag-heldout-v17; do sleep 120; done
while systemctl --user is-active --quiet kag-heldout-base || systemctl --user is-active --quiet kag-heldout-v16 || systemctl --user is-active --quiet kag-heldout-v17; do sleep 120; done
echo "$(date -u +%FT%T) validation terminée, libération"
systemctl --user stop kag-colab-tunnel kag-colab-keepalive
/home/elrems/.local/bin/colab4 stop -s v17
echo "$(date -u +%FT%T) session Colab v17 libérée"
