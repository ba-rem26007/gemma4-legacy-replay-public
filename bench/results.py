#!/usr/bin/env python3
"""Agrège les runs d'évaluation TEST → docs/RESULTATS.md (verdicts réévalués si disponibles).

Un bug est « résolu » si l'oracle passe ET qu'aucune régression n'est détectée (anti-régression avant l'oracle).
Usage : python3 bench/results.py
"""
import csv, json, random, statistics
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
RUNS = ROOT / "runs"
# essais par condition (essai 1 = run interrompu puis repris → union de deux dossiers)
TRIALS = {
    "A": [["20260925-092935-A", "20260925-101441-A"], ["20260925-114612-A"], ["20260925-142912-A"], ["20260925-165642-A"]],
    "R": [["20260925-092934-R", "20260925-101441-R"], ["20260925-114712-R"], ["20260925-143833-R"], ["20260925-171642-R"]],
}
LABEL = {"A": "A · ticket seul", "R": "R · ticket + 2 corrections TRAIN similaires"}
# condition O (borne haute B*) : 1 essai, oracle comme retour (2 corrections) ; union de deux dossiers (run repris)
O_DIRS = ["20260926-052040-O", "20260926-065152-O"]
# conditions à 1 essai, comparées à la moyenne de A (4 essais) ; dossiers ajoutés au fil des runs
SINGLE = {
    "B · Replay tests avec feedback dynamique (Gemma 4 31B)": ["20260928-092011-B"],
    "A-4B · Zero-Shot sans LoRA (Gemma 4 4B Base)": ["20260926-052040-A", "20260926-065152-A"],
    "E · Fine-tuné complet QLoRA (Gemma 4 4B)": ["20260928-175551-E"],
    "C · + glossaire automatique (provisoire)": ["20260926-103202-C"],
    "C+pages · + glossaire auto + index des pages BO": ["20260926-134839-C"],
}


def load(dirs, bugs):
    out = {}
    for d in dirs:
        for pr in bugs:
            base = RUNS / d / pr
            r0 = base / "result.json"
            if not r0.exists():
                continue
            r = json.loads(r0.read_text())
            rv = base / "result_reeval2.json"  # réévaluation après correction de checkout.sh (prioritaire)
            if not rv.exists():
                rv = base / "result_reeval.json"
            if rv.exists():
                v = json.loads(rv.read_text())
                r.update({k: v.get(k) for k in ("applied", "fixed", "regression")})
                r["reeval"] = True
            out[pr] = r
    return out


def main():
    bugs = [r["pr"] for r in csv.DictReader(open(ROOT / "data" / "bugs_test.csv")) if r["statut"] == "valide"]
    title = {r["pr"]: r["titre_ticket"] for r in csv.DictReader(open(ROOT / "data" / "bugs_test.csv"))}
    data = {c: [load(t, bugs) for t in TRIALS[c]] for c in TRIALS}
    solved = lambda r: bool(r and r.get("fixed") and not r.get("regression"))

    lines = ["# Résultats — vivier TEST 9.1.x (33 bugs, oracles cachés)", "",
             "Gemma 4 31B (API Google AI Studio), déroulé fixe, **1 tentative par bug** (pas de retour de test), température 0,2.",
             "« Résolu » = l'oracle passe **et** aucune régression (accueil FO + login BO) sur la base remise à zéro.",
             "Verdicts **réévalués** sans rappeler le modèle (`bench/reeval.py`) sur instances neuves quand disponibles.",
             "L'essai 1 a tourné sur un environnement partiellement défectueux : sa génération est valable, son verdict l'est après réévaluation.", ""]
    lines += ["## Taux de résolution par essai", "", "| Condition | Essai 1 | Essai 2 | Essai 3 | Essai 4 | Moyenne | Écart-type | Résolu ≥ 1 fois (pass@4) | Bon fichier (moy.) | Régressions |",
              "|---|---|---|---|---|---|---|---|---|---|"]
    per_bug = {}
    for c in TRIALS:
        counts, locs, regs, reev, n_eval = [], [], 0, 0, []
        for t in data[c]:
            counts.append(sum(solved(t.get(b)) for b in bugs))
            locs.append(sum(bool(t.get(b, {}).get("loc_hit")) for b in bugs))
            regs += sum(bool(t.get(b, {}).get("regression")) for b in bugs)
            reev += sum(bool(t.get(b, {}).get("reeval")) for b in bugs)
            n_eval.append(sum(b in t for b in bugs))
        ever = sum(any(solved(t.get(b)) for t in data[c]) for b in bugs)
        for b in bugs:
            per_bug.setdefault(b, {})[c] = sum(solved(t.get(b)) for t in data[c])
        cells = [f"{k}/{n}" for k, n in zip(counts, n_eval)]
        lines.append(f"| {LABEL[c]} | {' | '.join(cells)} | {statistics.mean(counts):.1f} ({statistics.mean(counts) / len(bugs):.0%}) | "
                     f"{statistics.pstdev(counts):.1f} | {ever}/{len(bugs)} | {statistics.mean(locs):.1f} | {regs} |")
    # écart R − A apparié par bug (bootstrap)
    diffs = [(per_bug[b]["R"] - per_bug[b]["A"]) / 4 for b in bugs]
    random.seed(0)
    boots = sorted(statistics.mean(random.choices(diffs, k=len(diffs))) for _ in range(5000))
    lo, hi = boots[125], boots[4875]
    lines += ["", "## R contre A (apparié par bug)", "",
              f"Écart moyen du taux de résolution R − A : **{statistics.mean(diffs):+.1%}** "
              f"(IC 95 % bootstrap : {lo:+.1%} à {hi:+.1%}).",
              "Si l'intervalle contient 0, l'injection de corrections similaires n'a pas d'effet démontré.", ""]
    never = [b for b in bugs if per_bug[b]["A"] == 0 and per_bug[b]["R"] == 0]
    always = [b for b in bugs if per_bug[b]["A"] == 4 and per_bug[b]["R"] == 4]
    lines += [f"- Bugs jamais résolus (0/8) : **{len(never)}**", f"- Bugs toujours résolus (8/8) : **{len(always)}**", "",
              "## Détail par bug (nombre d'essais résolus sur 4)", "", "| Bug | Ticket | A | R |", "|---|---|---|---|"]
    for b in sorted(bugs, key=lambda b: -(per_bug[b]["A"] + per_bug[b]["R"])):
        lines.append(f"| [#{b}](https://github.com/PrestaShop/PrestaShop/pull/{b}) | {title[b][:70]} | {per_bug[b]['A']} | {per_bug[b]['R']} |")
    lines += o_section(bugs, per_bug, solved)
    lines += single_section(bugs, data, per_bug, solved)
    export_csv(bugs, solved)
    (ROOT / "docs" / "RESULTATS.md").write_text("\n".join(lines) + "\n")
    print("\n".join(lines[:20]))


def o_section(bugs, per_bug, solved):
    """Borne haute O : 1re tentative (≈ condition A), puis après retour de l'oracle, puis verdict final réévalué."""
    o = load(O_DIRS, bugs)
    if not o:
        return []
    ok = lambda f: bool(f.get("fixed") and not f.get("regression"))
    first = [b for b in bugs if b in o and o[b].get("feedbacks") and ok(o[b]["feedbacks"][0])]
    after = [b for b in bugs if b in o and b not in first and any(ok(f) for f in o[b].get("feedbacks", []))]
    final = sum(solved(o.get(b)) for b in bugs)
    a_mean = statistics.mean(per_bug[b]["A"] for b in bugs) * len(bugs) / 4
    out = ["", "## Borne haute : l'oracle comme retour (condition O, 1 essai)", "",
           "L'agent reçoit le résultat de l'**oracle caché** après chaque tentative (fuite volontaire) et peut corriger 2 fois.",
           "C'est le meilleur retour qu'un vérificateur puisse donner : il borne l'apport de toute chaîne de tests.", "",
           "| | Bugs résolus |", "|---|---|",
           f"| A, moyenne des 4 essais | {a_mean:.1f}/{len(bugs)} |",
           f"| O, 1re tentative (même consigne que A) | {len(first)}/{len(bugs)} |",
           f"| O, après retour de l'oracle | **{len(first) + len(after)}/{len(bugs)}** (+{len(after)} : " + ", ".join(f"#{b}" for b in after) + ") |",
           f"| O, verdict final réévalué (dernier patch) | {final}/{len(bugs)} |", "",
           "Lecture : même un vérificateur parfait n'ajoute que quelques bugs ; les échecs restants ne trouvent pas le bon fichier "
           "ou ne savent pas corriger malgré le signal (voir `docs/ECHECS.md`)."]
    return out


def single_section(bugs, data, per_bug, solved):
    """Conditions à 1 essai contre A (moyenne des 4 essais) : résolus, bon fichier, bugs gagnés / perdus."""
    a_loc = statistics.mean(sum(bool(t.get(b, {}).get("loc_hit")) for b in bugs) for t in data["A"])
    a_mean = sum(per_bug[b]["A"] for b in bugs) / 4
    out = ["", "## Autres conditions (1 essai) contre A", "",
           "Gagné = résolu ici mais jamais par A (0/4) ; perdu = raté ici mais toujours résolu par A (4/4).", "",
           "| Condition | Bugs traités | Résolus | Bon fichier | Gagnés | Perdus |", "|---|---|---|---|---|---|",
           f"| A · moyenne des 4 essais | {len(bugs)} | {a_mean:.1f} | {a_loc:.1f} | — | — |"]
    for name, dirs in SINGLE.items():
        d = load(dirs, bugs)
        if not d:
            continue
        won = [b for b in d if solved(d[b]) and per_bug[b]["A"] == 0]
        lost = [b for b in d if not solved(d[b]) and per_bug[b]["A"] == 4]
        out.append(f"| {name} | {len(d)} | {sum(solved(r) for r in d.values())} | {sum(bool(r.get('loc_hit')) for r in d.values())} | "
                   f"{len(won)}{' (' + ', '.join('#' + b for b in won) + ')' if won else ''} | {len(lost)} |")
    return out


def export_csv(bugs, solved):
    """eval/results.csv (kit phase 8) : une ligne par (condition, essai, bug) ; base du notebook Kaggle."""
    runs = [(c, i + 1, t) for c in TRIALS for i, t in enumerate(TRIALS[c])] + [("O", 1, O_DIRS)] + \
           [(name.split(" ·")[0], 1, dirs) for name, dirs in SINGLE.items()]
    out = ROOT / "eval" / "results.csv"
    out.parent.mkdir(exist_ok=True)
    with open(out, "w", newline="") as fh:
        w = csv.writer(fh)
        w.writerow(["condition", "essai", "pr", "resolu", "bon_fichier", "patch_applique", "regression", "reevalue", "dossiers"])
        for c, i, dirs in runs:
            d = load(dirs, bugs)
            for b in bugs:
                if b in d:
                    r = d[b]
                    w.writerow([c, i, b, int(solved(r)), int(bool(r.get("loc_hit"))), int(bool(r.get("applied"))),
                                int(bool(r.get("regression"))), int(bool(r.get("reeval"))), "+".join(dirs)])


if __name__ == "__main__":
    main()
