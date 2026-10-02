#!/usr/bin/env bash
# Lance la condition C (glossaire automatique provisoire) quand le run O est terminé.
cd /home/elrems/kaggle
until grep -q '^→' runs/test_O1.log; do sleep 60; done
GLOSSAIRE=glossaire_auto.csv PSB=6 python3 -u agent/run.py --bugs $(cat runs/test_all.txt) --condition C --model gemma-4-31b-it --retries 0 >> runs/test_C1.log 2>&1
