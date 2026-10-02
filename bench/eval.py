#!/usr/bin/env python3
"""Évalue un patch sur un bug : checkout pre + patch, replay du bug, fumée FO/BO.

Usage : python3 bench/eval.py <pr> <patch.diff|pre|post>
Sortie JSON : {"pr", "applied", "fixed", "regression", "replay_error", "seconds"}
Contrôle : post → fixed=true ; pre (patch vide) → fixed=false.
"""
import json, os, re, subprocess, sys, time, urllib.request
from pathlib import Path

B = Path(__file__).resolve().parent
PSB = os.environ.get("PSB", "1")
PORT = int(os.environ.get("PS_PORT", 8080 + int(PSB)))


def sh(cmd, timeout=900):
    r = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=timeout)
    return r.returncode, r.stdout + r.stderr


def smoke():
    """Régression minimale : accueil FO et page de login BO répondent 200 sans erreur PHP."""
    for path in ("/fr/", "/admin-dev/index.php?controller=AdminLogin"):
        try:
            with urllib.request.urlopen(f"http://localhost:{PORT}{path}", timeout=30) as r:
                body = r.read().decode("utf-8", "ignore")
                if r.status != 200 or re.search(r"Fatal error|Parse error|Whoops", body):
                    return False
        except Exception:
            return False
    return True


def replay_error(out):
    """Message d'échec Playwright condensé (lisible par un agent)."""
    if re.search(r"^== .*oracle\w*\.php", out, re.M):  # oracle PHP : sa sortie (valeurs, exception) EST le retour utile
        return "\n".join(out.strip().splitlines()[-25:])[:1500]
    keep = [l for l in out.splitlines() if re.search(r"Error:|Expected|Received|✘|expect\(|Timeout", l)]
    return "\n".join(keep[:15])[:1500]


def evaluate(pr, patch, tests="oracle"):
    """Un seul accès à la stack Docker à la fois (verrou fichier)."""
    import fcntl
    with open(B / f".eval{PSB}.lock", "w") as lock:  # un verrou par instance
        fcntl.flock(lock, fcntl.LOCK_EX)
        return _evaluate(pr, patch, tests)


def _evaluate(pr, patch, tests="oracle"):
    """tests = "oracle" (verdict, caché à l'agent) ou "replay" (retour donné à l'agent en B/C/D/R)."""
    t = time.time()
    code, out = sh(f"{B}/checkout.sh {pr} {patch}", timeout=1800)
    if code != 0:
        return {"pr": pr, "applied": False, "fixed": False, "regression": None,
                "replay_error": out[-800:], "seconds": round(time.time() - t)}
    # anti-régression AVANT l'oracle : base remise à zéro + patch, sans l'état laissé par setup.sql / le test
    reg = not smoke()
    # vivier TRAIN : oracles écrits par Gemma (bench/gentest.py) dans bench/replay/g<pr>/ → ORACLE_PREFIX=g
    code, rout = sh(f"{B}/replay/run.sh {os.environ.get('ORACLE_PREFIX', '')}{pr} {tests}")
    return {"pr": pr, "applied": True, "fixed": code == 0, "regression": reg,
            "replay_error": "" if code == 0 else replay_error(rout), "seconds": round(time.time() - t)}


if __name__ == "__main__":
    print(json.dumps(evaluate(int(sys.argv[1]), sys.argv[2]), ensure_ascii=False, indent=1))
