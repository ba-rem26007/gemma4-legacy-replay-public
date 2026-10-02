#!/usr/bin/env bash
# SSH vers une session Colab via la CLI (tunnel websocket, clé dédiée ~/.ssh/colab_ed25519). Usage : tools/colab_ssh.sh <session> [args ssh…]
S=$1; shift
exec ssh -i ~/.ssh/colab_ed25519 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o LogLevel=ERROR -o ServerAliveInterval=30 -o ControlMaster=no -o ControlPath=none \
  -o "ProxyCommand=$HOME/.local/bin/colab4 ssh --proxy-mode -s $S -i $HOME/.ssh/colab_ed25519" "root@colab-$S" "$@"
