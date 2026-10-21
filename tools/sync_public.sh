#!/usr/bin/env bash
# Met à jour le dépôt PUBLIC (ba-rem26007/gemma4-legacy-replay-public) depuis HEAD du dépôt de travail :
# reconstruit l'arbre (liste blanche + contrôles de tools/build_public.sh) et pousse un nouveau commit.
# L'historique du dépôt public ne contient que ces commits « propres » (jamais l'historique du dépôt de travail).
# Usage : tools/sync_public.sh "message"     Publication à la soumission : gh repo edit ba-rem26007/gemma4-legacy-replay-public --visibility public --accept-visibility-change-consequences
set -euo pipefail
cd "$(dirname "$0")/.."
W=$(mktemp -d); trap 'rm -rf "$W"' EXIT
git clone -q https://github.com/ba-rem26007/gemma4-legacy-replay-public.git "$W/pub"
tools/build_public.sh "$W/tree" >/dev/null
rsync -a --delete --exclude .git "$W/tree/" "$W/pub/"
cd "$W/pub"; git add -A
git diff --cached --quiet && { echo "rien à publier"; exit 0; }
git -c user.name="$(git -C "$OLDPWD" config user.name)" -c user.email="$(git -C "$OLDPWD" config user.email)" commit -qm "${1:-sync $(git -C "$OLDPWD" rev-parse --short HEAD)}"
git push -q && git log --oneline -1
