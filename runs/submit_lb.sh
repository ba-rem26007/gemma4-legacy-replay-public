#!/usr/bin/env bash
# Soumission Leaderboard : envoie le bundle V8 (Top 10 bundle : max_tool_calls: 50 + git ls-files navigation + pytest collection error bypass)
# Quota : 1 soumission par jour UTC -> pas avant 00 h 00 UTC (2 h Paris).
set -e
cd /home/elrems/kaggle/kaggle
Z=/home/elrems/kaggle/kaggle/submission_v8.zip
[ "$(sha256sum $Z | cut -c1-12)" = "ec91c323e187" ] || { echo "zip V8 modifié, abandon"; exit 1; }
cp $Z /home/elrems/kaggle/kaggle/submission.zip
D=$(mktemp -d); cp $Z $D/submission.zip
PYTHONPATH=$HOME/.local/lib/ipv4only /home/elrems/.local/share/uv/tools/kaggle/bin/python - "$D/submission.zip" <<'PY'
import sys, json, datetime
from kaggle.api.kaggle_api_extended import KaggleApi
a = KaggleApi(); a.authenticate()
r = a.competition_submit(sys.argv[1], "V8 Top 10 bundle: max_tool_calls 50 + git ls-files + collection error bypass", "gemma-4-developer-agent")
print(r)
ref = getattr(r, "ref", None) or (r.get("ref") if isinstance(r, dict) else None)
with open("/home/elrems/kaggle/kaggle/submissions.jsonl", "a") as f:
    f.write(json.dumps({"ref": ref, "date": datetime.datetime.utcnow().strftime("%Y-%m-%dT%H:%M:%SZ"), "kernel": "direct_zip",
        "version": 8, "condition": "V8_top10_maxcalls50_collection_bypass", "model": "gemma-4-31b-it-qat-w4a16-ct",
        "description": "V8 Top 10 bundle: max_tool_calls 50 + git ls-files + collection error bypass", "zip_sha256_12": "ec91c323e187", "status": "PENDING", "cost_eur": 0.0}) + "\n")
PY
