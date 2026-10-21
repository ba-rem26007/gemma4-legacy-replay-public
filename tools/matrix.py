#!/usr/bin/env python3
"""Matrice paramètres × résultats de TOUS les runs d'agent → docs/MATRICE.md + eval/matrice.csv. Aucun modèle.

Une ligne par dossier de run (runs/AAAAMMJJ-HHMMSS-<C>/), paramètres reconstruits :
- jeu de bugs : TEST (33 bugs validés), TRAIN-validation (lot mis de côté v17), TRAIN-boucle (oracles Gemma), autre ;
- modèle et adaptateur (nom du modèle), condition, reprises (retours de test observés), oracle (caché / Gemma) ;
- version de l'agent : dernier commit touchant agent/ avant le lancement — sauf répétitions B/O du 2 oct. (agent figé, AGENT_COMMIT) ;
- taille de lecture MAX_LINES_PER_FILE (260 avant 36a9f7c du 28/09 20 h 55, 120 ensuite), fenêtre WINDOW (30 puis 20) ;
- résultats : bugs traités, résolus (verdict réévalué si disponible, sans régression), bon fichier lu, patch appliqué, régressions.
Puis une table agrégée par expérience (mêmes paramètres). Les dossiers runs/_* (invalides, doublons) sont ignorés.
Usage : python3 tools/matrix.py
"""
import collections, csv, json, statistics, subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
TEST = [r["pr"] for r in csv.DictReader(open(ROOT / "data" / "bugs_test.csv")) if r["statut"] == "valide"]
HELD = {str(b) for b in json.load(open(ROOT / "data" / "heldout_train_v17.json"))["bugs"]}
REP = {"20261002-082406-B": "4f649d9", "20261002-082409-B": "4f649d9", "20261002-082412-O": "f7dcbdc"}
AGENT_LOG = [(l.split()[1] + "T" + l.split()[2][:8], l.split()[0]) for l in
             subprocess.run(["git", "-C", str(ROOT), "log", "--format=%h %ad", "--date=iso", "--", "agent/"],
                            capture_output=True, text=True).stdout.splitlines()]


def agent_at(run_id):
    ts = f"{run_id[:4]}-{run_id[4:6]}-{run_id[6:8]}T{run_id[9:11]}:{run_id[11:13]}:{run_id[13:15]}"
    return next((h for d, h in AGENT_LOG if d <= ts), "?")


def verdict(d):
    r = json.loads((d / "result.json").read_text())
    for n in ("result_reeval2.json", "result_reeval.json"):
        if (d / n).exists():
            r.update({k: json.loads((d / n).read_text()).get(k) for k in ("applied", "fixed", "regression")})
            break
    return r


def main():
    rows = []
    for run in sorted(p for p in (ROOT / "runs").glob("2026*-*") if p.is_dir()):
        bugs = [d for d in run.iterdir() if d.is_dir() and d.name.isdigit() and (d / "result.json").exists()]
        if not bugs:
            continue
        rs = {d.name: verdict(d) for d in bugs}
        names = set(rs)
        jeu = ("TEST" if names <= set(TEST) else "TRAIN-validation" if names <= HELD and run.name >= "20261002" else
               "TRAIN-boucle" if all((ROOT / "bench" / "replay" / f"g{b}").exists() for b in names) else "autre")
        any_r = next(iter(rs.values()))
        model = any_r.get("model", "?")
        cond = run.name.rsplit("-", 1)[1]
        rid = run.name
        commit = REP.get(rid) or (("4f649d9" if cond == "B" else "f7dcbdc") if rid.startswith("20261002-13") and cond in "BO" else agent_at(rid))
        after = rid >= "20260928-205506"
        fixed = sum(bool(r.get("fixed") and not r.get("regression")) for r in rs.values())
        rows.append({"run": rid, "jeu": jeu, "modele": model, "condition": cond,
                     "adaptateur": "v15" if "v15" in model or model == "gemma-4-ft" else "v16" if "v16" in model else "v17" if "v17" in model else "aucun",
                     "agent": commit, "max_lines": 120 if after and commit not in ("4f649d9", "f7dcbdc") else 260,
                     "window": 20 if after and commit not in ("4f649d9", "f7dcbdc") else 30,
                     "oracle": "Gemma (g)" if jeu.startswith("TRAIN") else "caché",
                     "bugs": len(rs), "resolus": fixed, "taux": round(fixed / len(rs), 3),
                     "bon_fichier": sum(bool(r.get("loc_hit")) for r in rs.values()),
                     "patch_applique": sum(bool(r.get("applied")) for r in rs.values()),
                     "regressions": sum(bool(r.get("regression")) for r in rs.values()),
                     "retours_test": sum(len(r.get("feedbacks") or []) for r in rs.values())})
    with open(ROOT / "eval" / "matrice.csv", "w", newline="") as f:
        w = csv.DictWriter(f, fieldnames=list(rows[0])); w.writeheader(); w.writerows(rows)

    key = lambda r: (r["jeu"], r["modele"], r["condition"], r["adaptateur"], r["agent"], r["max_lines"])
    groups = collections.defaultdict(list)
    for r in rows:
        if r["jeu"] in ("TEST", "TRAIN-validation") and r["bugs"] >= 5:   # essais ponctuels (1-4 bugs) : détail seulement
            groups[key(r)].append(r)
    L = ["# Matrice paramètres × résultats (générée par `python3 tools/matrix.py`)", "",
         "Source : tous les dossiers `runs/2026*` (verdicts réévalués si disponibles ; « résolu » = oracle OK et aucune régression).",
         "Les runs partiels (interrompus puis repris dans un autre dossier) apparaissent séparément : pour les chiffres officiels du",
         "papier (essais fusionnés) voir `docs/RESULTATS.md`, `docs/RESULTATS_E4B.md` et `notebook/verification.ipynb`.", "",
         "## Synthèse par expérience (TEST et validation TRAIN ; runs d'au moins 5 bugs)", "",
         "| Jeu | Modèle | Cond. | Adaptateur | Agent | Lecture (lignes) | Runs (bugs traités) | Résolus / run | Taux moyen | Bon fichier moy. | Régr. |",
         "|---|---|---|---|---|---|---|---|---|---|---|"]
    for k, rs in sorted(groups.items()):
        rate = statistics.mean(r["taux"] for r in rs)
        L.append(f"| {k[0]} | {k[1]} | {k[2]} | {k[3]} | `{k[4]}` | {k[5]} | {len(rs)} ({', '.join(str(r['bugs']) for r in rs)}) | "
                 f"{', '.join(str(r['resolus']) for r in rs)} | {rate:.1%} | {statistics.mean(r['bon_fichier'] / r['bugs'] for r in rs):.0%} | "
                 f"{sum(r['regressions'] for r in rs)} |")
    L += ["", "## Paramètres fixes (tous les runs)", "",
          "Déroulé fixe LOCALISER → LIRE (≤ 3 fichiers) → ÉDITER (SEARCH/REPLACE) → test ; 2 reprises (`--retries 2`) ;",
          "température 0,2 ; recherche : 25 fichiers max. Conditions : A ticket seul · R +2 correctifs TRAIN · C +glossaire ·",
          "B +test de repro écrit par Gemma comme retour · O +oracle comme retour (plafond) · E +règles métier/glossaire/spec (petits modèles).",
          "", "## Détail par dossier de run", "",
          "| Run | Jeu | Modèle | Cond. | Adapt. | Agent | Lecture | Bugs | Résolus | Bon fichier | Patch appliqué | Régr. |",
          "|---|---|---|---|---|---|---|---|---|---|---|---|"]
    for r in rows:
        L.append(f"| {r['run']} | {r['jeu']} | {r['modele']} | {r['condition']} | {r['adaptateur']} | `{r['agent']}` | {r['max_lines']} | "
                 f"{r['bugs']} | {r['resolus']} | {r['bon_fichier']} | {r['patch_applique']} | {r['regressions']} |")
    (ROOT / "docs" / "MATRICE.md").write_text("\n".join(L) + "\n")
    print("\n".join(L[:9 + len(groups)]))


if __name__ == "__main__":
    main()
