#!/usr/bin/env python3
"""Rendement de la boucle d'auto-apprentissage (Gemma seul) → docs/BOUCLE.md. Aucun modèle.

Étape 1 : oracles écrits par Gemma (bench/gentest.jsonl, dernier essai par bug) — par mode (ui / explore / php).
Étape 2 : agent Gemma en condition O sur TRAIN avec ces oracles (runs/*-O dont les bugs ont un dossier bench/replay/g<pr>).
Étape 3 : chemins acceptés par trajectories/self_paths.py (garde-fous : vérifié, fichiers du correctif officiel).
Usage : python3 bench/loop_stats.py
"""
import collections, csv, json, subprocess, sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
TEST = {int(r["pr"]) for r in csv.DictReader(open(ROOT / "data" / "bugs_test.csv"))}


def mode_of(pr):
    d = ROOT / "bench" / "replay" / f"g{pr}"
    if list(d.glob("oracle_gemma*.php")):
        return "php"
    return "ui"


def main():
    last = {}
    for l in open(ROOT / "bench" / "gentest.jsonl"):
        if l.strip().startswith("{"):
            r = json.loads(l)
            last[r["pr"]] = r
    # mode : fichier d'oracle présent, sinon journal du pilote (--explore / php)
    logs = {"explore": ROOT / "runs" / "gentest_explore.log", "php": ROOT / "runs" / "gentest_php.log",
            "php2": ROOT / "runs" / "gentest_php2.log"}
    tagged = {}
    for m, p in logs.items():
        if p.exists():
            for l in open(p):
                if l.startswith("{"):
                    tagged[json.loads(l)["pr"]] = "php" if m.startswith("php") else m
    by = collections.defaultdict(collections.Counter)
    for pr, r in last.items():
        m = tagged.get(pr) or mode_of(pr)
        by[m][r["statut"]] += 1
    valid = sorted(pr for pr, r in last.items() if r["statut"] == "valide")

    # étape 2 : runs O sur des bugs TRAIN à oracle Gemma
    agent = {}
    for d in sorted((ROOT / "runs").glob("*-O")):
        for b in d.iterdir():
            if b.is_dir() and b.name.isdigit() and int(b.name) not in TEST and (b / "result.json").exists():
                r = json.loads((b / "result.json").read_text())
                agent[int(b.name)] = (d.name, bool(r.get("fixed") and not r.get("regression")))
    runs = sorted({v[0] for v in agent.values()})
    # étape 3 : exporteur (sortie jetable, on ne garde que les compteurs)
    out3 = "(aucun run)"
    if runs:
        # régénère trajectories/self.jsonl depuis TOUS les runs O TRAIN (l'exporteur refuse les bugs TEST)
        p = subprocess.run([sys.executable, str(ROOT / "trajectories" / "self_paths.py"), *[str(ROOT / "runs" / r) for r in runs]],
                           capture_output=True, text=True)
        out3 = (p.stdout.strip() or p.stderr.strip()[-300:]).replace(str(ROOT) + "/", "")

    L = ["# Boucle d'auto-apprentissage — rendement (Gemma 4 31B seul)", "",
         "Régénéré par `python3 bench/loop_stats.py`.", "",
         "## Étape 1 — oracles écrits par Gemma (validés : échoue avant correctif, passe après)", "",
         "| Mode | Validés | Échecs | Erreurs | Taux |", "|---|---|---|---|---|"]
    for m in ("ui", "explore", "php"):
        c = by.get(m, collections.Counter())
        n = sum(c.values())
        if n:
            L.append(f"| {m} | {c['valide']} | {c['echec']} | {c['erreur']} | {c['valide'] / n:.0%} |")
    L += ["", f"Oracles validés : {', '.join('#' + str(p) for p in valid) or '—'}", "",
          "## Étape 2 — agent Gemma avec l'oracle comme retour (condition O, TRAIN)", "",
          "| Bug | Run | Résolu (oracle) |", "|---|---|---|"]
    L += [f"| #{pr} | {run} | {'oui' if ok else 'non'} |" for pr, (run, ok) in sorted(agent.items())]
    L += ["", "## Étape 3 — chemins acceptés (`trajectories/self_paths.py`)", "", f"`{out3}`", "",
          "Garde-fous : verdict réévalué, éditions limitées aux fichiers du correctif officiel (anti-contournement),",
          "blocs SEARCH/REPLACE reproduisant exactement le patch, < 8 000 tokens, aucun bug TEST."]
    (ROOT / "docs" / "BOUCLE.md").write_text("\n".join(L) + "\n")
    print("\n".join(L))


if __name__ == "__main__":
    main()
