#!/usr/bin/env python3
"""Trajectoires Gemma COMPLÈTES (avec exploration) pour la v17 — runs O sur le vivier TRAIN.

Contrairement à self_paths.py (chemin condensé : tentatives ratées et retours retirés), on garde ici TOUTE la conversation
d'un run réussi : relocalisations, relectures, retours de test (oracle Gemma) et corrections. But : apprendre au modèle à
explorer et à se corriger, pas seulement à éditer du premier coup (la v16, entraînée sur des chemins « idéaux », perd
de la localisation : docs/RESULTATS_E4B.md).
Garde-fous (identiques à self_paths.build) : verdict réévalué OK, éditions finales dans les fichiers ET fonctions du correctif
officiel, aucune fuite (bug TEST, merge après la coupure, fonction corrigée après la coupure), bugs mis de côté exclus
(data/heldout_train_v17.json). Seules des sorties de Gemma (+ messages du déroulé) : aucune sortie de modèle propriétaire.
Usage : python3 trajectories/unrolled_paths.py [--out trajectories/unrolled.jsonl]
"""
import argparse, csv, json, sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "trajectories"))
sys.path.insert(0, str(ROOT / "agent"))
import flow  # noqa: E402
import self_paths  # noqa: E402


def trajectory(d):
    """Conversation complète du run : système + (user, assistant) de chaque tour de trace.jsonl."""
    msgs = [{"role": "system", "content": flow.SYSTEM}]
    steps = []
    for l in open(d / "trace.jsonl"):
        t = json.loads(l)
        msgs += [{"role": "user", "content": t["user"]}, {"role": "assistant", "content": t["assistant"] or ""}]
        steps.append(t["step"])
    return msgs, steps


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--cutoff", default="2025-06-01")
    ap.add_argument("--max-tokens", type=int, default=8000)
    ap.add_argument("--out", default=str(ROOT / "trajectories" / "unrolled.jsonl"))
    a = ap.parse_args()
    cat = {c["pr"]: c for c in map(json.loads, open(ROOT / "bench" / "catalog.jsonl"))}
    test = {int(r["pr"]) for r in csv.DictReader(open(ROOT / "data" / "bugs_test.csv"))}
    held = set(json.load(open(ROOT / "data" / "heldout_train_v17.json"))["bugs"])
    post = [c for c in cat.values() if (c["merged_at"] or "9999") >= a.cutoff]
    post_fns = {(f, fn) for c in post for f, fns in c["functions"].items() for fn in fns if not fn.startswith("(")}
    best, stats = {}, {}
    for run in sorted((ROOT / "runs").glob("*-O")):
        for d in sorted(p for p in run.iterdir() if p.is_dir() and p.name.isdigit() and (p / "result.json").exists()):
            pr, bug = int(d.name), cat.get(int(d.name))
            if not bug:
                why = "hors_catalogue"
            elif pr in test or (bug["merged_at"] or "9999") >= a.cutoff or \
                    {(f, fn) for f, fns in bug["functions"].items() for fn in fns} & post_fns:
                why = "exclu_etancheite"
            elif pr in held:
                why = "mis_de_cote_validation"
            elif not self_paths.verdict(d):
                why = "non_resolu"
            else:
                msgs, why = self_paths.build(bug, d)   # mêmes garde-fous (fichiers / fonctions officiels, reproductible)
                if msgs:
                    full, steps = trajectory(d)
                    tok = sum(len(m["content"]) for m in full) // 3
                    if tok > a.max_tokens:
                        why = "trop_long"
                    else:
                        why = "ok"
                        explo = [s for s in steps if s in ("relocaliser", "relire", "corriger")]
                        cand = {"pr": pr, "issue": bug["issue"], "merged_at": bug["merged_at"], "source": "gemma_unrolled",
                                "run": run.name, "tokens_est": tok, "steps": steps, "exploration": len(explo),
                                "similarite": self_paths.similarity(d, pr), "messages": full}
                        if pr not in best or tok < best[pr]["tokens_est"]:   # un exemple par bug : le plus court
                            best[pr] = cand
            stats[why] = stats.get(why, 0) + 1
    rows = sorted(best.values(), key=lambda r: r["pr"])
    with open(a.out, "w") as f:
        for r in rows:
            f.write(json.dumps(r, ensure_ascii=False) + "\n")
    print(f"{len(rows)} trajectoires complètes → {a.out} ({sum(r['exploration'] > 0 for r in rows)} avec exploration)  {stats}")


if __name__ == "__main__":
    main()
