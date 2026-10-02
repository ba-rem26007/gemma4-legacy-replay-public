#!/usr/bin/env python3
"""Valide un test de reproduction enregistré par l'expert (bench/replay/<pr>/replay_expert*.spec.js).

Reproduit = échoue sur le code d'origine (pre). Fidélité (analyse) = passe avec le correctif officiel (post).
Usage : PSB=7 python3 bench/validate_expert.py <pr> [<pr>…]  → bench/replay/<pr>/STATUS_EXPERT
"""
import os, subprocess, sys
from pathlib import Path

B = Path(__file__).resolve().parent
PSB = os.environ.get("PSB", "7")


def run(pr, mode):
    subprocess.run(f"PSB={PSB} {B}/checkout.sh {pr} {mode}", shell=True, capture_output=True)
    return subprocess.run(f"PSB={PSB} {B}/replay/run.sh {pr} replay_expert", shell=True, capture_output=True, text=True).returncode


for pr in sys.argv[1:]:
    if not list((B / "replay" / pr).glob("replay_expert*.spec.js")):
        print(f"#{pr} : aucun replay_expert*.spec.js"); continue
    pre, post = run(pr, "pre"), run(pr, "post")
    statut = "reproduit" if pre != 0 else "ne_reproduit_pas"
    (B / "replay" / pr / "STATUS_EXPERT").write_text(f"{statut}\npasse avec correctif officiel : {post == 0}\n")
    print(f"#{pr} : {statut} ; fidèle (passe en post) : {post == 0}")
