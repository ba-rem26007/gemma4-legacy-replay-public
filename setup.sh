#!/usr/bin/env bash
# Installation après clone : dépôt PrestaShop (historique complet, ~1 Go) + Playwright.
# Usage : ./setup.sh [--no-playwright]
set -euo pipefail
cd "$(dirname "$0")"
[ -f .env ] || cp .env.local.example .env
if [ ! -d bench/ps/.git ]; then
  git clone --no-checkout https://github.com/PrestaShop/PrestaShop.git bench/ps
fi
git -C bench/ps fetch --tags origin
if [ "${1:-}" != "--no-playwright" ] && command -v npm >/dev/null; then
  (cd bench/replay && npm ci && npx playwright install chromium)
fi
echo "OK. Fine-tuning : voir training/README.md ; agent : python3 agent/run.py --help"
