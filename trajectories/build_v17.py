#!/usr/bin/env python3
"""Données d'entraînement v17 (décision du 2 oct., DECISIONS.md) — déterministe, aucune sortie de modèle propriétaire.

1. Chemins reconstruits depuis les correctifs officiels TRAIN (trajectories/reconstruct.build), régénérés avec le déroulé
   ACTUEL d'agent/flow.py (mêmes messages qu'à l'évaluation), sans plafond 4 096 (le noyau filtre à MAX_LEN 8 192).
2. Augmentation « fausse piste » (40 % des chemins, tirage déterministe par bug) : à l'étape LIRE le chemin choisit d'abord
   le fichier le mieux classé par la recherche qui N'EST PAS dans le correctif officiel ; à l'étape ÉDITER il répond
   {"files": [bons fichiers]} (retour arrière prévu par le déroulé), relit, puis édite. Cible : la localisation (la v16
   la dégrade, docs/RESULTATS_E4B.md) et l'habitude de vérifier qu'on lit le bon fichier.
3. + trajectories/self.jsonl (chemins Gemma condensés) et trajectories/unrolled.jsonl (trajectoires Gemma complètes).
Exclusions : bugs TEST, merges ≥ coupure, fonctions corrigées après la coupure (même règle que reconstruct.main),
bugs mis de côté pour la validation (data/heldout_train_v17.json).
Sortie : training/kaggle_dataset_v17/{train_v17.jsonl,self.jsonl,unrolled.jsonl} + rapport.
Usage : python3 trajectories/build_v17.py
"""
import collections, csv, hashlib, json, sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "agent"))
sys.path.insert(0, str(ROOT / "trajectories"))
import flow  # noqa: E402
import reconstruct  # noqa: E402

CUTOFF, DETOUR_FRAC = "2025-06-01", 0.40
OUT = ROOT / "training" / "kaggle_dataset_v17"


def detour_pick(pr):
    return int(hashlib.sha256(f"v17-{pr}".encode()).hexdigest(), 16) % 1000 < DETOUR_FRAC * 1000


def with_detour(bug, msgs):
    """Insère une fausse piste : lire d'abord le meilleur fichier NON officiel de la recherche, puis revenir."""
    files = json.loads(msgs[4]["content"])["files"]
    hits = flow.grep(bug["base_commit"], json.loads(msgs[2]["content"])["keywords"])
    wrong = next((f for f in hits if f not in files and flow.show(bug["base_commit"], f)), None)
    if not wrong:
        return None
    kws = json.loads(msgs[2]["content"])["keywords"]
    wrong_read = flow.msg_read({wrong: flow.windows(flow.show(bug["base_commit"], wrong), kws)})
    return msgs[:4] + [{"role": "assistant", "content": json.dumps({"files": [wrong]}, ensure_ascii=False)},
                       {"role": "user", "content": wrong_read},
                       {"role": "assistant", "content": json.dumps({"files": files}, ensure_ascii=False)},
                       msgs[5], msgs[6]]


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    cat = [json.loads(l) for l in open(ROOT / "bench" / "catalog.jsonl")]
    test = {int(r["pr"]) for r in csv.DictReader(open(ROOT / "data" / "bugs_test.csv"))}
    held = set(json.load(open(ROOT / "data" / "heldout_train_v17.json"))["bugs"])
    post = [c for c in cat if (c["merged_at"] or "9999") >= CUTOFF]
    post_fns = {(f, fn) for c in post for f, fns in c["functions"].items() for fn in fns if not fn.startswith("(")}
    stats, out = collections.Counter(), []
    for c in cat:
        if (c["merged_at"] or "9999") >= CUTOFF:
            continue
        if c["pr"] in test or {(f, fn) for f, fns in c["functions"].items() for fn in fns} & post_fns:
            stats["exclu_etancheite"] += 1; continue
        if c["pr"] in held:
            stats["mis_de_cote_validation"] += 1; continue
        msgs, why = reconstruct.build(c)
        if not msgs:
            stats[why] += 1; continue
        src = "reconstruit"
        if detour_pick(c["pr"]):
            d = with_detour(c, msgs)
            if d:
                msgs, src = d, "reconstruit_fausse_piste"
        stats[src] += 1
        out.append({"pr": c["pr"], "issue": c["issue"], "merged_at": c["merged_at"], "source": src,
                    "tokens_est": sum(len(m["content"]) for m in msgs) // 3, "messages": msgs})
    with open(OUT / "train_v17.jsonl", "w") as f:
        for o in out:
            f.write(json.dumps(o, ensure_ascii=False) + "\n")
    n_self = 0
    with open(OUT / "self.jsonl", "w") as f:
        for l in open(ROOT / "trajectories" / "self.jsonl"):
            if json.loads(l)["pr"] not in held:
                f.write(l); n_self += 1
    unrolled = [l for l in open(ROOT / "trajectories" / "unrolled.jsonl") if json.loads(l)["pr"] not in held]
    (OUT / "unrolled.jsonl").write_text("".join(unrolled))
    print(f"train_v17 : {len(out)} chemins {dict(stats)} ; self : {n_self} ; unrolled : {len(unrolled)}")


if __name__ == "__main__":
    main()
