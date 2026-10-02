#!/usr/bin/env python3
"""Réévalue les patchs déjà produits (aucun appel LLM) : utile quand l'évaluateur change.

Usage : PSB=n python3 bench/reeval.py runs/<run_A> [runs/<run_B> …]
Écrit <run>/<pr>/result_reeval.json (ou $REEVAL_OUT) et affiche le tableau.
REEVAL_OUT=result_reeval2.json : 2e réévaluation après correction de checkout.sh (fichiers hors correctif officiel restaurés).
"""
import json, os, sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parent))
from eval import evaluate

CAT = {json.loads(l)["pr"]: json.loads(l).get("merged_at") or "" for l in open(Path(__file__).resolve().parent / "catalog.jsonl")}
OUT = os.environ.get("REEVAL_OUT", "result_reeval.json")
for run in sys.argv[1:]:
    # du plus ancien au plus récent (montée de version incrémentale, pas de réinstallation)
    dirs = [d for d in Path(run).iterdir() if d.is_dir() and d.name.isdigit()]
    for d in sorted(dirs, key=lambda d: CAT.get(int(d.name), "")):
        p = d / "patch.diff"
        if not p.exists() or (d / OUT).exists():
            continue
        try:
            r = evaluate(int(d.name), str(p)) if p.read_text().strip() else {"pr": int(d.name), "applied": False, "fixed": False, "regression": None}
        except Exception as e:  # délai dépassé (machine chargée) : pas de verdict écrit → repris au passage suivant
            print(run, d.name, "ÉCHEC ÉVAL", type(e).__name__, flush=True)
            continue
        (d / OUT).write_text(json.dumps(r, ensure_ascii=False, indent=1))
        print(run, json.dumps({k: r.get(k) for k in ("pr", "applied", "fixed", "regression")}), flush=True)
