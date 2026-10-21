#!/usr/bin/env bash
# Soumission Leaderboard : envoie le bundle V9 (Fix Wheelhouse v29/v30: thinking_budget: 0 + max_time 5.5m + heredoc python)
set -e
cd /home/elrems/kaggle/kaggle
Z=/home/elrems/kaggle/kaggle/submission_v9.zip
[ "$(sha256sum $Z | cut -c1-12)" = "648fa7d8fd4a" ] || { echo "zip V9 modifié, abandon"; exit 1; }
cp $Z /home/elrems/kaggle/kaggle/submission.zip
D=$(mktemp -d); cp $Z $D/submission.zip
PYTHONPATH=$HOME/.local/lib/ipv4only /home/elrems/.local/share/uv/tools/kaggle/bin/python - "$D/submission.zip" <<'PY'
import sys, json, datetime
from kaggle.api.kaggle_api_extended import KaggleApi
a = KaggleApi(); a.authenticate()
desc = "V9: Zero-Thinking Multi-Lib Agent (thinking_budget 0 + max_time 5.5m + heredoc python edits + py_compile)"
r = a.competition_submit(sys.argv[1], desc, "gemma-4-developer-agent")
print(r)
ref = getattr(r, "ref", None) or (r.get("ref") if isinstance(r, dict) else None)
if ref:
    with open("/home/elrems/kaggle/kaggle/submissions.jsonl", "a") as f:
        f.write(json.dumps({
            "ref": ref,
            "date": datetime.datetime.now(datetime.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
            "kernel": "direct_zip",
            "version": 12,
            "condition": "V9_zero_thinking_budget0_maxtime5_5",
            "model": "gemma-4-31b-it-qat-w4a16-ct",
            "description": desc,
            "zip_sha256_12": "648fa7d8fd4a",
            "status": "PENDING",
            "cost_eur": 0.0
        }) + "\n")
PY
