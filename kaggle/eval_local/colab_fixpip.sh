#!/usr/bin/env bash
# Le Python de Colab n'a pas ensurepip → les venvs des sandboxes swegemma n'ont pas pip, et `pip install -e /workspace`
# retombe sur le pip de l'hôte : le dépôt de la tâche s'installe dans le Python système (constaté le 3 oct. : `requests`
# remplacé par la copie d'une sandbox puis supprimé → harnais cassé ; tâches requests contaminées entre harnais parallèles).
apt-get -qq update >/dev/null 2>&1
apt-get -qq install -y python3.13-venv >/dev/null 2>&1 || apt-get -qq install -y python3-venv >/dev/null 2>&1
python3 -c "import ensurepip;print('ensurepip ok')"
python3 -m venv /tmp/vt && /tmp/vt/bin/python -m pip --version && rm -rf /tmp/vt
for p in $(pip list -e 2>/dev/null | awk "NR>2{print \$1}" | grep -v gemma4-swe-kit); do echo "désinstalle éditable $p"; pip uninstall -y "$p"; done
pip install -q --no-deps --force-reinstall requests==2.32.5
python3 -c "import requests;print('requests', requests.__file__)"
echo "FIXPIP TERMINÉ"
