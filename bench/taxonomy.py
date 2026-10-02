#!/usr/bin/env python3
"""Taxonomie des échecs (kit phase 8) à partir des traces et verdicts réévalués. Aucun modèle.

Catégories (première qui s'applique) :
  resolu              oracle OK, pas de régression
  regression          oracle OK mais anti-régression KO
  mauvais_fichier     aucun fichier lu n'appartient au correctif officiel
  aucune_edition      pas de bloc SEARCH/REPLACE exploitable (boucle de relecture, format)
  patch_inapplicable  blocs produits mais SEARCH introuvable / patch refusé
  correctif_faux      patch appliqué au bon endroit, oracle KO (logique fausse ou partielle)
Usage : python3 bench/taxonomy.py → docs/ECHECS.md
"""
import collections, json, sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "bench"))
from results import TRIALS, load  # noqa: E402
import csv

ORDER = ["resolu", "regression", "mauvais_fichier", "aucune_edition", "patch_inapplicable", "correctif_faux"]
LABEL = {"resolu": "Résolu", "regression": "Régression", "mauvais_fichier": "Mauvais fichier (localisation)",
         "aucune_edition": "Aucune édition exploitable", "patch_inapplicable": "Patch inapplicable",
         "correctif_faux": "Correctif appliqué mais faux"}


def classify(r):
    if r.get("fixed") and not r.get("regression"):
        return "resolu"
    if r.get("fixed"):
        return "regression"
    if not r.get("loc_hit"):
        return "mauvais_fichier"
    err = (r.get("replay_error") or "") + (r.get("edit_errors") or "")
    if "aucune édition" in err or ("SEARCH" not in err and not r.get("applied")):
        return "aucune_edition" if "aucune" in err or not r.get("applied") and "SEARCH" not in err else "patch_inapplicable"
    if not r.get("applied") or "SEARCH introuvable" in err:
        return "patch_inapplicable"
    return "correctif_faux"


def main():
    bugs = [r["pr"] for r in csv.DictReader(open(ROOT / "data" / "bugs_test.csv")) if r["statut"] == "valide"]
    lines = ["# Taxonomie des échecs — vivier TEST (4 essais × 33 bugs par condition)", "",
             "Classement automatique (`bench/taxonomy.py`) à partir des traces et des verdicts réévalués.", "",
             "| Catégorie | " + " | ".join(TRIALS) + " |", "|---|" + "---|" * len(TRIALS)]
    counts = {c: collections.Counter() for c in TRIALS}
    per_bug = collections.defaultdict(collections.Counter)
    for c in TRIALS:
        for t in TRIALS[c]:
            d = load(t, bugs)
            for b in bugs:
                if b in d:
                    k = classify(d[b])
                    counts[c][k] += 1
                    per_bug[b][k] += 1
    for k in ORDER:
        lines.append(f"| {LABEL[k]} | " + " | ".join(f"{counts[c][k]} ({counts[c][k] / max(1, sum(counts[c].values())):.0%})" for c in TRIALS) + " |")
    lines += ["", "## Lecture", "",
              "- **Mauvais fichier** : l'agent ne lit jamais le fichier corrigé → cible du glossaire (condition C).",
              "- **Aucune édition / patch inapplicable** : problème de déroulé ou de format (copie SEARCH inexacte, boucles de relecture).",
              "- **Correctif faux** : bon endroit, mauvaise logique → cible d'un vérificateur fidèle (conditions B / O).", "",
              "## Catégorie dominante par bug (8 tentatives A+R)", "", "| Bug | Dominante | Détail |", "|---|---|---|"]
    for b in sorted(bugs, key=lambda b: -per_bug[b]["resolu"]):
        dom = per_bug[b].most_common(1)[0][0]
        lines.append(f"| #{b} | {LABEL[dom]} | " + ", ".join(f"{LABEL[k]} {n}" for k, n in per_bug[b].most_common()) + " |")
    (ROOT / "docs" / "ECHECS.md").write_text("\n".join(lines) + "\n")
    print("\n".join(lines[:14]))


if __name__ == "__main__":
    main()
