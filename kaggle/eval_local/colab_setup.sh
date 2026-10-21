#!/usr/bin/env bash
# Installation de l'éval locale Leaderboard sur une VM Colab A100 (piloté depuis le serveur par la CLI Colab).
# Reproduit l'évaluateur Kaggle : Python 3.12, vLLM 0.19.1, harnais officiel swegemma (dataset metric/gemma-4-developer-agent-wheelhouse),
# modèle gemma-4-31B-it-qat-w4a16-ct, sandbox subprocess (pas de Docker sur Colab), + kit public gemma4-swe-kit.
# Entrées sur la VM : /root/.kaggle/access_token, /content/lb/lot.json, $HF_TOKEN. Journal : /content/lb/setup.log
set -euo pipefail   # pas de -x : la trace afficherait le jeton Kaggle (incident du 2 oct.)
cd /content/lb
export KAGGLE_API_TOKEN=$(cat /root/.kaggle/access_token)
pip -q install -U uv kaggle
uv python install 3.12
# NB : Colab impose UV_SYSTEM_PYTHON → les paquets vont dans le Python système (3.13), pas dans un venv 3.12.
# Écart assumé avec l'évaluateur (3.12) ; vLLM 0.19.1 (abi3) et swegemma (py3) s'installent quand même.
# 1. harnais officiel + pile de l'évaluateur
kaggle datasets download metric/gemma-4-developer-agent-wheelhouse -p wheelhouse --unzip -q
uv pip install "vllm==0.19.1"                       # tire torch compatible
uv pip install --find-links wheelhouse swegemma==0.2.7 adk-submission==0.2.12 adk-eval-core==0.1.0 google-adk==1.36.1
# versions de l'évaluateur (vLLM 0.19.1 plante avec transformers 5.17 : AmbiguousGlobalPerLayerAttributeError head_dim)
pip -q install --no-deps wheelhouse/transformers-5.13.1-py3-none-any.whl wheelhouse/tokenizers-0.22.2-*.whl
git clone -q https://github.com/damsolanke/gemma4-swe-kit.git kit && uv pip install -e "kit[tokenizer]"
# 2. données du concours : tâches, wheels des dépôts, bootstrap du sandbox, exemple de soumission, snapshots du lot
mkdir -p comp && cd comp
kaggle competitions download gemma-4-developer-agent -f tasks.jsonl -q
for f in $(python3 -c "import json;print(' '.join(x['snapshot'] for x in json.load(open('/content/lb/lot.json'))))"); do
  kaggle competitions download gemma-4-developer-agent -f "$f" -p snapshots -q
done
cd /content/lb
# wheels/ + sandbox/ + sample_submission/ : fichiers légers, un par un
python3 - <<'PY'
import subprocess, re
tok = None
while True:
    out = subprocess.run(["kaggle", "competitions", "files", "gemma-4-developer-agent", "-v", "--page-size", "200"] + (["--page-token", tok] if tok else []),
                         capture_output=True, text=True).stdout
    for l in out.splitlines():
        name = l.split(",")[0]
        if name.startswith(("wheels/", "sandbox/", "sample_submission/")) or name in ("HARNESS_README.md",):
            d = "comp/" + name.rsplit("/", 1)[0] if "/" in name else "comp"
            subprocess.run(["kaggle", "competitions", "download", "gemma-4-developer-agent", "-f", name, "-p", d, "-q"])
    m = re.search(r"Next Page Token = (\S+)", out); tok = m.group(1) if m else None
    if not tok: break
PY
find comp -name "*.zip" -exec sh -c 'cd "$(dirname "$1")" && python3 -m zipfile -e "$(basename "$1")" . && rm "$(basename "$1")"' _ {} \;
# venvs des sandboxes avec pip (sinon `pip install -e` vise le Python système, cf. colab_fixpip.sh)
apt-get -qq install -y python3.13-venv >/dev/null 2>&1 || apt-get -qq install -y python3-venv >/dev/null 2>&1
# 3. modèle de l'évaluateur
hf download google/gemma-4-31B-it-qat-w4a16-ct --local-dir models/gemma-4-31b-it-qat-w4a16-ct
hf download google/gemma-4-31B-it-qat-w4a16-ct chat_template.jinja --revision e3dacad5f03b852209f5ce18e44094fc80120037 --local-dir kit/assets
echo "INSTALLATION TERMINÉE $(date -u +%FT%T)"
