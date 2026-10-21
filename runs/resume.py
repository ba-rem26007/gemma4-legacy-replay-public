#!/usr/bin/env python3
"""Reprise robuste (2 oct.) : relance UNIQUEMENT les bugs sans result.json d'un run interrompu, dans une unité systemd --user
(indépendante du terminal de l'agent IA : le 2 oct. à 13 h 50, tous les processus lancés depuis le terminal sont morts).
Le run repris est enregistré dans un NOUVEAU dossier ; bench/results.py fusionne les dossiers d'un même essai (« a+b »).
Usage : python3 runs/resume.py <nom_unite> <dossier_run_interrompu|-> <workdir> <condition> <modèle> <PSB> <bugs_file|heldout> [VAR=val …]
"""
import json, os, subprocess, sys
from pathlib import Path

ROOT = Path("/home/elrems/kaggle")
unit, prev, workdir, cond, model, psb, bugsrc, *envs = sys.argv[1:]
bugs = json.load(open(ROOT / "data/heldout_train_v17.json"))["bugs"] if bugsrc == "heldout" else open(ROOT / bugsrc).read().split()
done = set() if prev == "-" else {p.parent.name for p in (ROOT / prev).glob("*/result.json")}
todo = [str(b) for b in bugs if str(b) not in done]
print(f"{unit} : {len(done)} déjà faits, {len(todo)} à faire")
env = [f"--setenv={e}" for e in ["PSB=" + psb, "PYTHONUNBUFFERED=1", *envs]]
cmd = ["systemd-run", "--user", f"--unit={unit}", "--collect", f"--working-directory={workdir}", *env,
       "--property=StandardOutput=append:" + str(ROOT / "runs" / f"{unit}.log"),
       "--property=StandardError=append:" + str(ROOT / "runs" / f"{unit}.log"),
       "/usr/bin/python3", "-u", "agent/run.py", "--bugs", *todo, "--condition", cond, "--model", model, "--retries", "2"]
subprocess.run(cmd, check=True)
