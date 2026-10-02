#!/usr/bin/env bash
# Publie training/kaggle_kernel/train_kaggle.py dans le dataset Kaggle PRIVÉ rmisoubeyrand/gemma4-training-script
# (aucun GPU consommé) pour le test Colab en SMOKE=1 — cf. docs/FINETUNING_KAGGLE.md §0.
set -euo pipefail
cd "$(dirname "$0")/.."
D=$(mktemp -d); trap 'rm -rf "$D"' EXIT
cp training/kaggle_kernel/train_kaggle.py "$D/"
printf '{"title": "Gemma 4 PrestaShop training script", "id": "rmisoubeyrand/gemma4-training-script", "licenses": [{"name": "Apache 2.0"}]}\n' > "$D/dataset-metadata.json"
kaggle datasets version -p "$D" -m "${1:-script $(git rev-parse --short HEAD)}"
