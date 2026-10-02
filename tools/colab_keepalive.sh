#!/usr/bin/env bash
# Maintient une session Colab active pendant un long job lancé hors noyau (serveurs nohup) : un petit exec toutes les 10 min.
# Usage : tools/colab_keepalive.sh <session>
while true; do echo 'print("keepalive")' | timeout 120 "$HOME/.local/bin/colab4" exec -s "$1" >/dev/null 2>&1; sleep 600; done
