#!/usr/bin/env bash
# Soumission Leaderboard : envoie le bundle V14 (Retour strict au socle V12 éprouvé à 0.12 : git ls-files direct + découverte patiente 12m + rollback immédiat py_compile)
set -e
cd /home/elrems/kaggle/kaggle
Z=/home/elrems/kaggle/kaggle/submission_v14.zip
[ "$(sha256sum $Z | cut -c1-12)" = "d86d9ca8075e" ] || { echo "zip V14 modifié, abandon"; exit 1; }
cp $Z /home/elrems/kaggle/kaggle/submission.zip
D=$(mktemp -d); cp $Z $D/submission.zip
PYTHONPATH=$HOME/.local/lib/ipv4only /home/elrems/.local/share/uv/tools/kaggle/bin/python - "$D/submission.zip" <<'PY'
import sys, json, datetime
from kaggle.api.kaggle_api_extended import KaggleApi
a = KaggleApi(); a.authenticate()
desc = "V14: Proven V12 Baseline Replay (patient discovery + immediate py_compile rollback protection)"
r = a.competition_submit(sys.argv[1], desc, "gemma-4-developer-agent")
print(r)
ref = getattr(r, "ref", None) or (r.get("ref") if isinstance(r, dict) else None)
if ref:
    with open("/home/elrems/kaggle/kaggle/submissions.jsonl", "a") as f:
        f.write(json.dumps({
            "ref": ref,
            "date": datetime.datetime.now(datetime.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
            "kernel": "direct_zip",
            "version": 17,
            "condition": "V14_proven_v12_baseline_replay",
            "model": "gemma-4-31b-it-qat-w4a16-ct",
            "description": desc,
            "zip_sha256_12": "d86d9ca8075e",
            "status": "PENDING",
            "cost_eur": 0.0
        }) + "\n")
PY
