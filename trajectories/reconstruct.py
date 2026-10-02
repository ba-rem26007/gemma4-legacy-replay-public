#!/usr/bin/env python3
"""Chemins RECONSTRUITS (kit phase 6.2) depuis les correctifs officiels du vivier TRAIN. Aucun modèle.

Pour chaque bug TRAIN : ticket → mots-clés (symboles touchés) → recherche (git grep réel) → lecture des
fichiers officiels → édition = correctif officiel en blocs SEARCH/REPLACE, au format EXACT de agent/flow.py.
Contrôle qualité : on applique les blocs et on vérifie que le résultat == fichier du commit correctif.

Usage : python3 trajectories/reconstruct.py --cutoff 2025-06-01 [--max-tokens 8000]
Sortie : trajectories/train.jsonl, trajectories/STATS.md, data/ETANCHEITE.md
"""
import argparse, collections, json, re, sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "agent"))
import flow  # noqa: E402

CODE = (".php", ".tpl", ".twig", ".js", ".ts", ".vue")


def hunks(diff):
    """{fichier: [(lignes_old_touchées, search, replace)]} depuis un diff unifié."""
    out, f, cur = collections.defaultdict(list), None, None
    for l in diff.splitlines():
        if l.startswith("diff --git"):
            f = None
        elif l.startswith("+++ "):
            f = l[6:] if l.startswith("+++ b/") else None
        elif l.startswith("--- "):
            continue
        elif l.startswith("@@") and f:
            old = int(re.match(r"@@ -(\d+)", l).group(1))
            cur = {"old": old, "n": old, "s": [], "r": [], "touched": []}
            out[f].append(cur)
        elif cur is not None and f and l[:1] in (" ", "-", "+", ""):
            body = l[1:]
            if l.startswith("-"):
                cur["s"].append(body); cur["touched"].append(cur["n"] - 1); cur["n"] += 1
            elif l.startswith("+"):
                cur["r"].append(body); cur["touched"].append(cur["n"] - 1)
            elif not l.startswith("\\"):
                cur["s"].append(body); cur["r"].append(body); cur["n"] += 1
    return {f: [(h["touched"], "\n".join(h["s"]), "\n".join(h["r"])) for h in hs] for f, hs in out.items()}


def keywords(bug):
    kws = []
    for fns in bug["functions"].values():
        for fn in fns:
            if fn.startswith("("):
                continue
            for part in fn.split("::"):
                p = re.sub(r"Core$", "", part)
                if len(p) >= 4 and p not in ("__construct", "constructor") and p not in kws:
                    kws.append(p)
    GENERIC = {"index", "controller", "admin", "address", "product", "main", "form", "list", "utils", "helpers", "base", "module"}
    for f in bug["files"]:
        if f.startswith(flow.EXCLUDE):
            continue
        p = Path(f)
        stem = p.name.split(".")[0]
        # nom générique → fragment de chemin « dossier/nom »
        kw = f"{p.parent.name}/{stem}" if stem.lower() in GENERIC or len(stem) < 5 else re.sub(r"Core$", "", stem)
        if kw not in kws:
            kws.insert(0, kw)
    return kws[:6]


def edit_text(blocks):
    return "\n\n".join(f"FILE: {f}\n<<<<<<< SEARCH\n{s}\n=======\n{r}\n>>>>>>> REPLACE" for f, s, r in blocks)


def make_blocks(f, src, file_hunks):
    """Hunks d'un fichier → blocs SEARCH/REPLACE dont le SEARCH est unique dans src (contexte ajouté au besoin)."""
    rows, blocks = src.splitlines(), []
    for touched_lines, s, r in file_hunks:
        k = 0
        while s.strip() and src.count(s) > 1 and k < 8:
            # ajoute une ligne de contexte avant et après, identique des deux côtés
            k += 1
            i = src.find(s)
            start = src[:i].count("\n")
            end = start + s.count("\n") + 1
            before = rows[start - 1] if start > 0 else None
            after = rows[end] if end < len(rows) else None
            if before is not None:
                s, r = before + "\n" + s, before + "\n" + r
            if after is not None:
                s, r = s + "\n" + after, r + "\n" + after
        if not s.strip() or src.count(s) != 1:
            return None
        blocks.append((f, s, r))
    return blocks


def build(bug):
    base, merge = bug["base_commit"], bug["merge_commit"]
    diff = (ROOT / "bench" / "diffs" / f"{bug['pr']}.diff").read_text()
    hs = {f: h for f, h in hunks(diff).items() if f.endswith(CODE) and not f.startswith(flow.EXCLUDE)}
    if not hs or len(hs) > flow.MAX_FILES_READ:
        return None, "hors_format"
    kws = keywords(bug)
    hits = flow.grep(base, kws)
    files = list(hs)
    if any(f not in hits for f in files):
        return None, "fichier_absent_recherche"
    blocks, contents = [], {}
    for f in files:
        src = flow.show(base, f)
        if not src:
            return None, "fichier_nouveau"
        touched = [i for t, _, _ in hs[f] for i in t]
        contents[f] = flow.windows(src, kws, extra_lines=touched)
        b = make_blocks(f, src, hs[f])
        if b is None:
            return None, "search_non_unique"
        blocks += b
    # contrôle : les blocs reproduisent exactement le fichier corrigé
    state, errors = flow.apply_edits(base, flow.parse_edits(edit_text(blocks)))
    if errors or any(state[f].rstrip("\n") != flow.show(merge, f).rstrip("\n") for f in files):
        return None, "non_reproductible"
    msgs = [{"role": "system", "content": flow.SYSTEM},
            {"role": "user", "content": flow.msg_ticket(bug, "C")},
            {"role": "assistant", "content": json.dumps({"keywords": kws}, ensure_ascii=False)},
            {"role": "user", "content": flow.msg_grep(hits)},
            {"role": "assistant", "content": json.dumps({"files": files}, ensure_ascii=False)},
            {"role": "user", "content": flow.msg_read(contents)},
            {"role": "assistant", "content": edit_text(blocks)}]
    return msgs, "ok"


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--cutoff", required=True, help="date de coupure Gemma 4 (AAAA-MM-JJ) : TRAIN = mergés avant")
    ap.add_argument("--max-tokens", type=int, default=8000)
    a = ap.parse_args()
    cat = [json.loads(l) for l in open(ROOT / "bench" / "catalog.jsonl")]
    test = [c for c in cat if (c["merged_at"] or "9999") >= a.cutoff]
    train = [c for c in cat if (c["merged_at"] or "9999") < a.cutoff]

    # Étanchéité : on exclut tout bug TRAIN qui touche une même FONCTION qu'un bug TEST
    test_fns = {(f, fn) for c in test for f, fns in c["functions"].items() for fn in fns if not fn.startswith("(")}
    test_prs, test_issues = {c["pr"] for c in test}, {c["issue"] for c in test}
    stats, out, excluded = collections.Counter(), [], []
    for c in train:
        overlap = {(f, fn) for f, fns in c["functions"].items() for fn in fns} & test_fns
        if c["pr"] in test_prs or c["issue"] in test_issues or overlap:
            stats["exclu_etancheite"] += 1
            excluded.append((c["pr"], sorted(f"{f}:{fn}" for f, fn in overlap)[:3]))
            continue
        msgs, why = build(c)
        stats[why] += 1
        if not msgs:
            continue
        tok = sum(len(m["content"]) for m in msgs) // 3
        if tok > a.max_tokens:
            stats["trop_long"] += 1; stats["ok"] -= 1
            continue
        out.append({"pr": c["pr"], "issue": c["issue"], "merged_at": c["merged_at"], "source": "reconstruit",
                    "tokens_est": tok, "messages": msgs})

    d = ROOT / "trajectories"
    with open(d / "train.jsonl", "w") as f:
        for o in out:
            f.write(json.dumps(o, ensure_ascii=False) + "\n")
    toks = sorted(o["tokens_est"] for o in out) or [0]
    (d / "STATS.md").write_text(f"""# Chemins d'entraînement

Coupure : **{a.cutoff}**. TRAIN = {len(train)} bugs, TEST = {len(test)} bugs (catalogue `bench/catalog.jsonl`).

| Source | Chemins |
|---|---|
| reconstruit | {len(out)} |
| auto | 0 (runs de l'agent à venir) |

Rejets : {dict(stats)}

Longueur estimée (tokens ≈ caractères / 3) : médiane {toks[len(toks) // 2]}, max {toks[-1]}, plafond {a.max_tokens}.
Chaque chemin est **vérifié** : ses blocs SEARCH/REPLACE reproduisent exactement le fichier du commit correctif.
Format identique à `agent/flow.py` (déroulé fixe, condition C). Entraînement : loss sur les tours `assistant` uniquement.
""")
    (ROOT / "data").mkdir(exist_ok=True)
    (ROOT / "data" / "ETANCHEITE.md").write_text(f"""# Étanchéité TRAIN / TEST

- **Split temporel** : coupure au {a.cutoff}. TEST = bugs mergés à cette date ou après ({len(test)}), TRAIN = avant ({len(train)}).
- **Non-recouvrement** : tout bug TRAIN qui touche une même fonction (fichier + Classe::méthode) qu'un bug TEST est exclu,
  ainsi que tout bug partageant la PR ou l'issue. Exclus : **{stats['exclu_etancheite']}**.
- Le glossaire (`glossary_mine.py --gitlog`) doit être extrait avec `git log --before={a.cutoff}`.
- Méthode déterministe (`trajectories/reconstruct.py`), relancée à chaque changement de coupure ou de catalogue.

Exemples d'exclusions : {excluded[:10]}
""")
    print(f"TRAIN {len(train)} / TEST {len(test)} → {len(out)} chemins ; {dict(stats)}")


if __name__ == "__main__":
    main()
