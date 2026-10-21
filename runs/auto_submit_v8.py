#!/usr/bin/env python3
"""Attente automatique de la réinitialisation du quota journalier Kaggle
puis soumission du bundle V8 Top 10 à 02:02:00 heure de Paris (00:02:00 UTC).
"""
import datetime
import subprocess
import sys
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
LOG_FILE = ROOT / "runs" / "auto_submit_v8.log"

def log(msg):
    ts_utc = datetime.datetime.now(datetime.timezone.utc).strftime("%Y-%m-%d %H:%M:%S UTC")
    line = f"[{ts_utc}] {msg}"
    print(line, flush=True)
    with open(LOG_FILE, "a", encoding="utf-8") as f:
        f.write(line + "\n")

def main():
    now = datetime.datetime.now(datetime.timezone.utc)
    # Cible exacte : 00:02:00 UTC (02:02 Paris)
    target = (now + datetime.timedelta(days=1)).replace(hour=0, minute=2, second=0, microsecond=0)
    delay = (target - now).total_seconds()
    log(f"Planificateur V8 initialisé pour 02h02 Paris (00h02 UTC). Cible : {target.strftime('%Y-%m-%d %H:%M:%S UTC')} (dans {delay:.0f}s / {delay/60:.1f}min)")

    if delay > 0:
        time.sleep(delay)

    log("02h02 Paris atteint (00h02 UTC). Lancement de runs/submit_lb.sh...")
    cmd = ["bash", str(ROOT / "runs" / "submit_lb.sh")]
    res = subprocess.run(cmd, cwd=str(ROOT), capture_output=True, text=True)
    log(f"Code de retour : {res.returncode}")
    log(f"Sortie standard :\n{res.stdout}")
    if res.stderr:
        log(f"Erreur standard :\n{res.stderr}")

    if res.returncode == 0:
        log("Soumission V8 Top 10 envoyée avec succès sur Kaggle !")
    else:
        log("Échec de la soumission V8. Vérifier les logs ci-dessus.")

if __name__ == "__main__":
    main()
