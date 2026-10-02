#!/usr/bin/env bash
# Affiche la fin d'un journal sur une session Colab (CLI colab, IPv4 forcé), sans les barres de progression.
# Usage : tools/colab_tail.sh <session> <fichier> [lignes]
S=$1; F=$2; N=${3:-25}
printf 'import re\ntry:\n    t=open("%s").read()\nexcept FileNotFoundError:\n    t="(pas encore de journal)"\nt=re.sub(r"[^\\n]*\\r","",t)\nL=[l for l in t.splitlines() if not re.search(r"Loading weights|Map:|Filter:|it/s\\]$",l)]\nprint("\\n".join(L[-%s:]))\n' "$F" "$N" \
  > "${TMPDIR:-/tmp}/colab_tail_$$.py"
# 3 essais : la connexion websocket au noyau Colab se coupe parfois (« Connection was lost »)
for i in 1 2 3; do
  out=$(timeout 120 "$HOME/.local/bin/colab4" exec -s "$S" -f "${TMPDIR:-/tmp}/colab_tail_$$.py" 2>&1) && { echo "$out"; break; }
  sleep 10
done
rm -f "${TMPDIR:-/tmp}/colab_tail_$$.py"
