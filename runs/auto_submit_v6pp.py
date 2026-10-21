#!/usr/bin/env python3
"""Planificateur de soumission automatique du bundle V6'' (V6pp) :
Kaggle restreint la compétition à :
1. 1 seule soumission par jour (quota UTC réinitialisé à 00:00:00 UTC).
2. 0 soumission concurrente : "Your team already has 1 pending submissions; please wait for one to finish scoring."

Ce démon surveille la fin de l'évaluation de la soumission V6' (56993660).
Dès que le statut PENDING est levé et que le quota est ouvert, il soumet automatiquement V6pp.
"""
import datetime
import subprocess
import sys
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
LOG_FILE = ROOT / "runs" / "auto_submit_v6pp.log"
PYTHON_BIN = "/home/elrems/.local/share/uv/tools/kaggle/bin/python"

def log(msg):
    ts_utc = datetime.datetime.now(datetime.timezone.utc).strftime("%Y-%m-%d %H:%M:%S UTC")
    line = f"[{ts_utc}] {msg}"
    print(line, flush=True)
    with open(LOG_FILE, "a", encoding="utf-8") as f:
        f.write(line + "\n")

def get_pending_count():
    code = """
from kaggle.api.kaggle_api_extended import KaggleApi
api = KaggleApi()
api.authenticate()
subs = api.competition_submissions('gemma-4-developer-agent')
pending = [s for s in subs if 'PENDING' in str(s.status)]
print(len(pending))
"""
    try:
        res = subprocess.run(
            [PYTHON_BIN, "-c", code],
            cwd=str(ROOT),
            capture_output=True,
            text=True,
            timeout=60
        )
        if res.returncode == 0:
            return int(res.stdout.strip().splitlines()[-1])
    except Exception as e:
        log(f"Erreur vérification pending: {e}")
    return -1

def submit():
    cmd = ["bash", str(ROOT / "runs" / "submit_v7.sh")]
    res = subprocess.run(cmd, cwd=str(ROOT), capture_output=True, text=True)
    log(f"Code de retour : {res.returncode}")
    log(f"Sortie standard :\n{res.stdout}")
    if res.stderr:
        log(f"Erreur standard :\n{res.stderr}")
    return res.returncode == 0

def main():
    log("Démon auto_submit_v6pp (mode veille active sur fin de PENDING) démarré.")
    
    poll_interval = 60  # vérifier toutes les 60 secondes
    last_log_time = 0

    while True:
        pending = get_pending_count()
        now = time.time()
        
        # Log un statut toutes les 15 minutes pour ne pas saturer le fichier
        if now - last_log_time > 900 or pending == 0:
            log(f"Vérification Kaggle : {pending} soumission(s) PENDING en cours.")
            last_log_time = now

        if pending == 0:
            log("Plus aucune soumission PENDING ! Déclenchement immédiat de la soumission V6pp...")
            if submit():
                log(">>> SUCCÈS : Soumission V6pp envoyée et acceptée par Kaggle ! <<<")
                break
            else:
                log("Tentative de soumission échouée. Nouvelle tentative dans 120s...")
                time.sleep(120)
        else:
            time.sleep(poll_interval)

if __name__ == "__main__":
    main()
