#!/usr/bin/env python3
"""Planificateur de soumission automatique du bundle V13 (V10-Max) :
Programmé pour se déclencher exactement à 02h22 heure de Paris (00h22 UTC le 20 oct. 2026).
Kaggle réinitialise le quota quotidien à 00:00:00 UTC.
À 00:22 UTC (02h22 Paris), les files de soumission sont stables et le quota est ouvert.

Actions exécutées :
1. Attend 00:22:00 UTC (02h22 Paris).
2. Vérifie qu'aucune soumission n'est PENDING.
3. Soumet kaggle/submission_v13.zip via runs/submit_v13.sh.
4. Journalise le résultat dans runs/auto_submit_v13.log.
"""
import datetime
import subprocess
import sys
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
LOG_FILE = ROOT / "runs" / "auto_submit_v13.log"
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
    cmd = ["bash", str(ROOT / "runs" / "submit_v13.sh")]
    res = subprocess.run(cmd, cwd=str(ROOT), capture_output=True, text=True)
    log(f"Code de retour : {res.returncode}")
    log(f"Sortie standard :\n{res.stdout}")
    if res.stderr:
        log(f"Erreur standard :\n{res.stderr}")
    return res.returncode == 0

def main():
    log("Démon auto_submit_v13 initialisé. Cible : 02h22 heure de Paris (00h22:00 UTC)...")
    
    # Calcul de la cible 00:22:00 UTC
    now_utc = datetime.datetime.now(datetime.timezone.utc)
    target_utc = (now_utc + datetime.timedelta(days=1)).replace(hour=0, minute=22, second=0, microsecond=0)
    
    # Si nous sommes déjà passés minuit le même jour (par exemple entre 00:00 et 00:22)
    if now_utc.hour == 0 and now_utc.minute < 22:
        target_utc = now_utc.replace(hour=0, minute=22, second=0, microsecond=0)

    wait_sec = int((target_utc - now_utc).total_seconds())
    hours = wait_sec // 3600
    mins = (wait_sec % 3600) // 60
    secs = wait_sec % 60
    log(f"⏳ Temps d'attente jusqu'à 02h22 Paris (00h22 UTC) : {hours}h {mins:02d}m {secs:02d}s (soit {wait_sec} secondes).")

    while True:
        now_utc = datetime.datetime.now(datetime.timezone.utc)
        remaining = int((target_utc - now_utc).total_seconds())
        if remaining <= 0:
            break
        
        # Log de présence toutes les heures ou si < 10 minutes
        if remaining % 3600 < 60 or remaining <= 600:
            h = remaining // 3600
            m = (remaining % 3600) // 60
            log(f"En veille... réveil prévu dans {h}h {m:02d}m.")
            time.sleep(min(60, remaining))
        else:
            time.sleep(min(300, remaining))

    log("⏰ Heure cible atteinte (02h22 Paris / 00h22 UTC) ! Démarrage de la séquence de soumission V13...")
    
    max_retries = 30
    for attempt in range(1, max_retries + 1):
        pending = get_pending_count()
        log(f"Vérification soumissions en cours : {pending} PENDING (tentative {attempt}/{max_retries})")
        
        if pending == 0:
            log("🚀 Aucune soumission en cours. Envoi immédiat du bundle V13...")
            success = submit()
            if success:
                log("🎉 V13 soumise avec succès sur Kaggle ! Mission accomplie.")
                sys.exit(0)
            else:
                log("⚠️ Échec de l'envoi API Kaggle. Nouvelle tentative dans 60 secondes...")
        elif pending > 0:
            log(f"⏳ Une soumission est encore en statut PENDING ({pending}). Attente de libération (60s)...")
        else:
            log("⚠️ Impossible de vérifier le statut PENDING. Tentative quand même dans 60s...")
            
        time.sleep(60)

    log("❌ Échec : quota toujours bloqué ou erreur persistante après 30 tentatives.")
    sys.exit(1)

if __name__ == "__main__":
    main()
