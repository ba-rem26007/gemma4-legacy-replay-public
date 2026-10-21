#!/usr/bin/env bash
# Soumission Leaderboard : envoie le bundle V6'' (V6pp : V5 + git ls-files + max_tool_calls: 50 + max_time_minutes: 10)
set -e
cd /home/elrems/kaggle/kaggle
Z=/home/elrems/kaggle/kaggle/submission_v6pp.zip
[ "$(sha256sum $Z | cut -c1-12)" = "cc81f3a6a166" ] || { echo "zip V6pp modifié, abandon"; exit 1; }
cp $Z /home/elrems/kaggle/kaggle/submission.zip
D=$(mktemp -d); cp $Z $D/submission.zip
PYTHONPATH=$HOME/.local/lib/ipv4only /home/elrems/.local/share/uv/tools/kaggle/bin/python - "$D/submission.zip" <<'PY'
import sys, json, datetime
from kaggle.api.kaggle_api_extended import KaggleApi
a = KaggleApi(); a.authenticate()
desc = "V6pp: V5 + git ls-files + max_tool_calls 50 + max_time_minutes 10"
r = a.competition_submit(sys.argv[1], desc, "gemma-4-developer-agent")
print(r)
ref = getattr(r, "ref", None) or (r.get("ref") if isinstance(r, dict) else None)
if ref:
    with open("/home/elrems/kaggle/kaggle/submissions.jsonl", "a") as f:
        f.write(json.dumps({
            "ref": ref,
            "date": datetime.datetime.now(datetime.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
            "kernel": "direct_zip",
            "version": 10,
            "condition": "V6pp_lsfiles_maxcalls50_maxtime10",
            "model": "gemma-4-31b-it-qat-w4a16-ct",
            "description": desc,
            "zip_sha256_12": "cc81f3a6a166",
            "status": "PENDING",
            "cost_eur": 0.0
        }) + "\n")
PY
