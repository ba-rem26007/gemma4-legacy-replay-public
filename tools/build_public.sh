#!/usr/bin/env bash
# Construit l'arbre du dépôt PUBLIC (sans historique) depuis HEAD : liste blanche + contrôles.
# Usage : tools/build_public.sh [dossier_sortie]   (défaut : ../gemma4-legacy-replay-public)
# Rien n'est poussé : on inspecte, puis git init / push à la main.
set -euo pipefail
cd "$(dirname "$0")/.."
OUT="${1:-../gemma4-legacy-replay-public}"
KEEP=(LICENSE RELATED.md setup.sh .env.api.example .env.local.example agent bench data eval glossaire runs trajectories tools
      training/chunked_loss.py training/train_qlora.py training/kaggle_kernel training/kaggle_dataset training/snapshots
      training/lora_final/gemma-4-qlora-training-prestashop.log training/lora_final/lora_gemma4/final/README.md training/lora_v16/train_v16_colab_a100.log
      docs/KAGGLE_FINAL_WRITEUP.md docs/RESULTATS.md docs/BOUCLE.md docs/ECHECS.md docs/FINETUNING_KAGGLE.md
      docs/PROCEDURES.md docs/PROTOCOLE.md docs/RESULTATS_E4B.md notebook/verification.ipynb docs/GLOSSAIRE.md docs/VOCABULAIRE.md docs/DONNEES_FT.md docs/CATALOGUE.md
      docs/figures kaggle/eval_local kaggle/submission kaggle/submission_v3b kaggle/submission_v4 kaggle/submission_v5)
DROP='^(bench/test_context_leak_language\.php|bench/split_and_extract_catalogs\.py|trajectories/train_compact\.jsonl|trajectories/train_recovery\.jsonl|runs/leaderboard/.*)$'
rm -rf "$OUT"; mkdir -p "$OUT"
git ls-files -- "${KEEP[@]}" | grep -Ev "$DROP" | tar -cf - -T - | tar -xf - -C "$OUT"
cp docs/README_PUBLIC.md "$OUT/README.md"   # README public (anglais), distinct du README de travail
echo "fichiers : $(find "$OUT" -type f | wc -l)  taille : $(du -sh "$OUT" | cut -f1)"
fail=0
# 1) secrets
if grep -rIlE 'AIza[0-9A-Za-z_-]{30}|hf_[A-Za-z0-9]{30}|ghp_[A-Za-z0-9]{30}|KGAT_|sk-ant-|ngrok_?authtoken[^\n]{0,5}["=:] *"?[0-9A-Za-z_]{20}|d1dev`? */ *`?d1dev' "$OUT" --exclude=build_public.sh; then
  echo "ÉCHEC : secret possible (fichiers ci-dessus)"; fail=1; fi
# 2) aucun contenu sécurité (règle du projet) — hors listes de filtrage de select.py
if grep -rIliE 'zero-day|0-day|bounty|security advisor|vulnerabilit' "$OUT" --exclude=select.py --exclude=build_public.sh --exclude='*.jsonl' --exclude='*.diff'; then
  echo "ÉCHEC : contenu sécurité (fichiers ci-dessus)"; fail=1; fi
# 3) chemins cités par le writeup
for p in $(cat "$OUT/docs/KAGGLE_FINAL_WRITEUP.md" "$OUT/README.md" | grep -oE '`(agent|bench|data|docs|eval|runs|training|trajectories)/[A-Za-z0-9_./-]+`' | tr -d '`' | sort -u); do
  [ -e "$OUT/$p" ] || { echo "ÉCHEC : cité mais absent : $p"; fail=1; }; done
[ $fail = 0 ] && echo "OK : arbre public prêt dans $OUT" || exit 1
