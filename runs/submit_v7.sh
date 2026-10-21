#!/usr/bin/env bash
# Soumission Leaderboard : envoie le bundle V7 (Universel Multi-Libs : 5.5 min max, max_output_tokens 2048, git ls-files, heredoc python)
set -e
cd /home/elrems/kaggle/kaggle
Z=/home/elrems/kaggle/kaggle/submission_v7.zip
[ "$(sha256sum $Z | cut -c1-12)" = "f203d0d388a5" ] || { echo "zip V7 modifié, abandon"; exit 1; }
cp $Z /home/elrems/kaggle/kaggle/submission.zip
D=$(mktemp -d); cp $Z $D/submission.zip
PYTHONPATH=$HOME/.local/lib/ipv4only /home/elrems/.local/share/uv/tools/kaggle/bin/python - "$D/submission.zip" <<'PY'
import sys, json, datetime
from kaggle.api.kaggle_api_extended import KaggleApi
a = KaggleApi(); a.authenticate()
desc = "V7: Universal Multi-Lib Agent (max_time 5.5m + max_output_tokens 2048 + git ls-files + python heredoc edits)"
r = a.competition_submit(sys.argv[1], desc, "gemma-4-developer-agent")
print(r)
ref = getattr(r, "ref", None) or (r.get("ref") if isinstance(r, dict) else None)
if ref:
    with open("/home/elrems/kaggle/kaggle/submissions.jsonl", "a") as f:
        f.write(json.dumps({
            "ref": ref,
            "date": datetime.datetime.now(datetime.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
            "kernel": "direct_zip",
            "version": 11,
            "condition": "V7_universal_multilib_maxtime5_5",
            "model": "gemma-4-31b-it-qat-w4a16-ct",
            "description": desc,
            "zip_sha256_12": "f203d0d388a5",
            "status": "PENDING",
            "cost_eur": 0.0
        }) + "\n")
PY
