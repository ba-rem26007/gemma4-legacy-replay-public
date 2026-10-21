#!/usr/bin/env bash
# Complète colab_setup.sh : environnement des tâches équivalent à docker/Dockerfile.sandbox (Python 3.13), git, graphes/embeddings du lot.
set -euo pipefail
cd /content/lb
export KAGGLE_API_TOKEN=$(cat /root/.kaggle/access_token)
apt-get -qq install -y pigz protobuf-compiler patch > /dev/null 2>&1 || true
git config --global user.email "agent@eval"; git config --global user.name "Agent"; git config --global init.defaultBranch main
# le Python de Colab n'a pas ensurepip → venv sans pip + uv (UV_SYSTEM_PYTHON neutralisé pour viser ce venv)
rm -rf /content/lb/taskenv; python3 -m venv --without-pip /content/lb/taskenv
UV_SYSTEM_PYTHON=0 uv pip install -q --python /content/lb/taskenv/bin/python pytest "pytest-timeout==2.1.0" typer pdm-backend setuptools wheel poetry-core hatchling flit-core editables pip
for f in docker/imp.py docker/telnetlib.py; do kaggle competitions download gemma-4-developer-agent -f $f -p comp/docker -q; done
SP=$(/content/lb/taskenv/bin/python -c "import site;print(site.getsitepackages()[0])")
cp comp/docker/imp.py comp/docker/telnetlib.py "$SP/"
python3 - <<'PY'
import json, subprocess
t = {json.loads(l)["instance_id"]: json.loads(l) for l in open("comp/tasks.jsonl")}
for x in json.load(open("lot.json")):
    k = t[x["instance_id"]]; stem = f"{k['repo'].split('/')[-1]}_{k['base_commit']}"
    for d, e in (("graphs", "json"), ("embeddings", "npz")):
        subprocess.run(["kaggle", "competitions", "download", "gemma-4-developer-agent", "-f", f"{d}/{stem}.{e}", "-p", f"comp/{d}", "-q"])
PY
find comp/graphs comp/embeddings comp/docker -name "*.zip" -exec sh -c 'cd "$(dirname "$1")" && python3 -m zipfile -e "$(basename "$1")" . && rm "$(basename "$1")"' _ {} \; 2>/dev/null || true
ls comp/graphs | wc -l; ls comp/embeddings | wc -l
echo "PREP2 TERMINÉ $(date -u +%FT%T)"
