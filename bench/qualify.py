#!/usr/bin/env python3
"""Qualification déterministe des bugs du catalogue (colonnes du kit, phase 2).

Usage : python3 bench/qualify.py [--cutoff AAAA-MM-JJ]
Sortie : bench/qualified.csv (tous) ; avec --cutoff : data/bugs_test.csv + data/bugs_train.csv
Heuristiques par regex, aucun modèle. parcours_repro / difficulte_estimee sont des ESTIMATIONS à valider.
"""
import argparse, csv, json, re
from pathlib import Path

HERE = Path(__file__).parent
COLS = ["id_issue", "url_issue", "url_pr", "commit_avant", "commit_correctif", "date_correctif",
        "fichiers_touches", "zone", "parcours_repro", "difficulte_estimee",
        "pr", "branche", "fonctions", "nb_fonctions", "lignes", "titre_ticket", "titre_pr"]

BO = re.compile(r"\bBO\b|back[- ]?office|admin|catalog\s*[>›-]|orders?\s*[>›-]|customers?\s*[>›-]|sell\s*[>›-]|improve\s*[>›-]|shop parameters|advanced parameters|international\s*[>›-]|design\s*[>›-]|modules?\s*[>›-]", re.I)
FO = re.compile(r"\bFO\b|front[- ]?office|checkout|cart\b|product page|category page|my account|customer account|order history|guest|storefront|theme", re.I)
HTTP = re.compile(r"\bapi\b|webservice|\bWS\b|curl|endpoint|GET\s+/|POST\s+/|json", re.I)
CLI = re.compile(r"bin/console|\bcli\b|command line|cron|upgrade|install(?:er|ation)|composer", re.I)


def parcours(c):
    steps = c["ticket"]["steps"] + " " + c["ticket"]["title"]
    files = " ".join(c["files"])
    cat = (c["resolution"]["category"] or "").split("|")[0].strip().upper()
    if CLI.search(steps) and not (BO.search(steps) or FO.search(steps)):
        return "cli"
    if cat == "WS" or (HTTP.search(steps) and "Api" in files):
        return "http"
    bo, fo = bool(BO.search(steps)) or cat == "BO", bool(FO.search(steps)) or cat == "FO"
    if bo and fo:
        return "fo+bo"
    if bo:
        return "bo"
    if fo:
        return "fo"
    if "admin-dev/" in files or "/Admin" in files or "PrestaShopBundle/Controller/Admin" in files:
        return "bo?"
    if "controllers/front" in files or "themes/" in files:
        return "fo?"
    return "?"


def difficulte(c):
    nfn = sum(len(v) for v in c["functions"].values())
    nfi = len(c["files"])
    layers = {("legacy" if p.startswith(("classes/", "controllers/")) else "symfony" if p.startswith("src/")
               else "js" if p.endswith((".js", ".ts", ".vue")) else "tpl" if p.endswith((".tpl", ".twig")) else "autre")
              for p in c["files"]}
    score = (c["lines"] > 10) + (c["lines"] > 30) + (nfi > 1) + (nfn > 2) + (len(layers) > 1)
    return ["facile", "facile", "moyen", "moyen", "difficile", "difficile"][score]


def row(c):
    fns = [fn for fs in c["functions"].values() for fn in fs]
    return {
        "id_issue": c["issue"], "url_issue": f"https://github.com/PrestaShop/PrestaShop/issues/{c['issue']}",
        "url_pr": c["url"], "commit_avant": c["base_commit"], "commit_correctif": c["merge_commit"],
        "date_correctif": (c["merged_at"] or "")[:10], "fichiers_touches": "|".join(c["files"]),
        "zone": c["area"] if c["area"] != "?" else (c["resolution"]["category"] or "?").split("|")[0].strip(),
        "parcours_repro": parcours(c), "difficulte_estimee": difficulte(c),
        "pr": c["pr"], "branche": c["branch"], "fonctions": "|".join(fns), "nb_fonctions": len(fns),
        "lignes": c["lines"], "titre_ticket": c["ticket"]["title"], "titre_pr": c["resolution"]["title"],
    }


def write(path, rows):
    path.parent.mkdir(exist_ok=True)
    with open(path, "w", newline="") as f:
        w = csv.DictWriter(f, COLS); w.writeheader(); w.writerows(rows)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--cutoff", default=None)
    a = ap.parse_args()
    rows = sorted((row(json.loads(l)) for l in open(HERE / "catalog.jsonl")), key=lambda r: r["date_correctif"])
    write(HERE / "qualified.csv", rows)
    if a.cutoff:
        write(HERE.parent / "data" / "bugs_test.csv", [r for r in rows if r["date_correctif"] >= a.cutoff])
        write(HERE.parent / "data" / "bugs_train.csv", [r for r in rows if r["date_correctif"] < a.cutoff])
    print(f"{len(rows)} bugs → qualified.csv" + (f" ; split au {a.cutoff}" if a.cutoff else ""))


if __name__ == "__main__":
    main()
