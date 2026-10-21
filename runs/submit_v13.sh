#!/usr/bin/env bash
# Soumission Leaderboard : envoie le bundle V13 (V10-Max : socle robuste éprouvé à 0.12-0.13 + localisation rapide git grep -l + retours d'erreurs stderr + garde anti-patch vide)
set -e
cd /home/elrems/kaggle/kaggle
Z=/home/elrems/kaggle/kaggle/submission_v13.zip
[ "$(sha256sum $Z | cut -c1-12)" = "02cfa16a8fd0" ] || { echo "zip V13 modifié, abandon"; exit 1; }
cp $Z /home/elrems/kaggle/kaggle/submission.zip
D=$(mktemp -d); cp $Z $D/submission.zip
PYTHONPATH=$HOME/.local/lib/ipv4only /home/elrems/.local/share/uv/tools/kaggle/bin/python - "$D/submission.zip" <<'PY'
import sys, json, datetime
from kaggle.api.kaggle_api_extended import KaggleApi
a = KaggleApi(); a.authenticate()
desc = "V13: V10-Max Precision Agent (proven baseline + fast grep-l discovery + stderr error reporting + anti-empty guard)"
r = a.competition_submit(sys.argv[1], desc, "gemma-4-developer-agent")
print(r)
ref = getattr(r, "ref", None) or (r.get("ref") if isinstance(r, dict) else None)
if ref:
    with open("/home/elrems/kaggle/kaggle/submissions.jsonl", "a") as f:
        f.write(json.dumps({
            "ref": ref,
            "date": datetime.datetime.now(datetime.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
            "kernel": "direct_zip",
            "version": 16,
            "condition": "V13_v10_max_precision_agent",
            "model": "gemma-4-31b-it-qat-w4a16-ct",
            "description": desc,
            "zip_sha256_12": "02cfa16a8fd0",
            "status": "PENDING",
            "cost_eur": 0.0
        }) + "\n")
PY
