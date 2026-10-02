#!/usr/bin/env bash
# Condition C+pages (glossaire auto + index des pages BO) quand le run C est terminé.
cd /home/elrems/kaggle
until grep -q '^→' runs/test_C1.log; do sleep 60; done
GLOSSAIRE=glossaire_auto.csv,pages.csv PSB=6 python3 -u agent/run.py --bugs $(cat runs/test_all.txt) --condition C --model gemma-4-31b-it --retries 0 >> runs/test_Cp1.log 2>&1
