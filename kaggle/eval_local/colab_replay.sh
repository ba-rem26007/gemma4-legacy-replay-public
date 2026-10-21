#!/usr/bin/env bash
# VM neuve : installation + passage de collecte V3b (contextes d'édition capturés par slow_proxy --dump), puis rejeu des variantes.
cd /content/lb
bash colab_all.sh lent2 v3b=submission_v3b=1.0
python3 replay_edits.py edits_lent2.jsonl --n 4 --out replay_edits_lent2.jsonl > replay_lent2.txt 2>&1
echo "REJEU TERMINÉ $(date -u +%FT%T)" >> replay_lent2.txt
