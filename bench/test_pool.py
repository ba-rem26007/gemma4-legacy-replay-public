#!/usr/bin/env python3
"""Vivier TEST (kit phase 2) : bugs 9.1.x corrigés après la coupure, rejouables en Docker.

Usage : python3 bench/test_pool.py [--cutoff 2025-06-01] [--branch 9.1.x]
Sortie : data/bugs_test.csv (colonnes du kit + statut) ; le statut est conservé entre deux exécutions.
Filtres (déterministes) :
  - branche unique, fusion >= coupure
  - fichiers PHP/tpl/twig uniquement (les images Docker embarquent le JS compilé : .ts/.js/.vue non rejouables)
  - parcours reproductible estimé : fo / bo / fo+bo / http
  - pas de sécurité (déjà filtré par select.py)
statut : candidat → replay_ecrit → valide (échoue en pre, passe en post) | exclu:<raison>
"""
import argparse, csv, json
from pathlib import Path

HERE = Path(__file__).parent
OUT = HERE.parent / "data" / "bugs_test.csv"
SERVER = (".php", ".tpl", ".twig")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--cutoff", default="2025-06-01")
    ap.add_argument("--branch", default="9.1.x")
    a = ap.parse_args()
    q = {r["pr"]: r for r in csv.DictReader(open(HERE / "qualified.csv"))}
    old = {r["pr"]: r for r in csv.DictReader(open(OUT))} if OUT.exists() else {}
    rows, excl = [], {}
    for c in map(json.loads, open(HERE / "catalog.jsonl")):
        r = dict(q[str(c["pr"])])
        if c["branch"] != a.branch or r["date_correctif"] < a.cutoff:
            continue
        reason = None
        if not all(f.endswith(SERVER) for f in c["files"]):
            reason = "exclu:js_compile"
        elif r["parcours_repro"] not in ("fo", "bo", "fo+bo", "http"):
            reason = "exclu:parcours_inconnu"
        prev = old.get(str(c["pr"]), {}).get("statut", "")
        st = HERE / "replay" / str(c["pr"]) / "STATUS"  # écrit lors de la validation pre/post de l'oracle
        if st.exists():
            prev = st.read_text().splitlines()[0].strip() or prev
        r["statut"] = prev if prev and not prev.startswith("exclu:js") else (reason or "candidat")
        if r["statut"].startswith("exclu"):
            excl[r["statut"]] = excl.get(r["statut"], 0) + 1
        rows.append(r)
    rows.sort(key=lambda r: (r["statut"] != "valide", r["statut"].startswith("exclu"), r["date_correctif"]))
    with open(OUT, "w", newline="") as f:
        w = csv.DictWriter(f, list(rows[0].keys())); w.writeheader(); w.writerows(rows)
    ok = [r for r in rows if not r["statut"].startswith("exclu")]
    print(f"{len(rows)} bugs {a.branch} ≥ {a.cutoff} → {len(ok)} rejouables ; exclus {excl}")


if __name__ == "__main__":
    main()
