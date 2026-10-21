#!/usr/bin/env bash
# Chaîne complète sur une VM Colab neuve : installation, préparation, puis passage <tag> avec les bras donnés.
# Usage (VM) : bash colab_all.sh <tag> "<bras>=<dossier>=<time_scale>" …     Journal : /content/lb/all.log
cd /content/lb
bash colab_setup.sh > setup.log 2>&1 || { echo "ÉCHEC setup"; exit 1; }
bash colab_prep2.sh > prep2.log 2>&1 || { echo "ÉCHEC prep2"; exit 1; }
tar xzf subs.tgz
python3 -c "import transformers,tokenizers,requests;print(transformers.__version__,tokenizers.__version__)"
bash colab_run.sh "$@"
