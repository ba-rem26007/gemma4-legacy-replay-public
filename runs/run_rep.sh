#!/usr/bin/env bash
# Répétition d'une condition avec le code d'agent EXACT de son run 1 (arbre /home/elrems/kaggle-rep/<C>, agent/ figé au commit
# indiqué dans AGENT_COMMIT ; bench/, runs/, données = dépôt principal). Usage : runs/run_rep.sh <B|O> <PSB> <tag>
set -e
C=$1; export PSB=$2 PYTHONUNBUFFERED=1; TAG=$3
D=/home/elrems/kaggle-rep/$C
echo "=== $C ($TAG) agent $(cat $D/AGENT_COMMIT) sur psbench$PSB, $(date -u +%FT%T) ==="
cd $D && python3 -u agent/run.py --bugs $(cat /home/elrems/kaggle/runs/test_all.txt) --condition $C --model gemma-4-31b-it --retries 2
echo "=== FIN $C ($TAG) $(date -u +%FT%T) ==="
