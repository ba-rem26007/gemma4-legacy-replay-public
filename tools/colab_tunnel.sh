#!/usr/bin/env bash
# Tunnel SSH privé (reconnexion auto) : localhost:1800N du serveur → port 800N de la VM Colab <session>.
# Usage : tools/colab_tunnel.sh <session> 8001 8002 8003   (arrêt : pkill -f "colab_tunnel.sh <session>")
S=$1; shift
L=(); for p in "$@"; do L+=(-L "1$p:localhost:$p"); done
while true; do
  "$(dirname "$0")/colab_ssh.sh" "$S" -N -o ExitOnForwardFailure=yes "${L[@]}"
  echo "$(date -u +%FT%T) tunnel coupé, reconnexion dans 10 s" >&2; sleep 10
done
