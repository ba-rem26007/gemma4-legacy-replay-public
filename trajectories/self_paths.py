#!/usr/bin/env python3
"""Chemins GEMMA vérifiés (boucle d'auto-apprentissage) : runs de l'agent sur le vivier TRAIN → chemins condensés.

Pour chaque bug résolu (oracle OK, pas de régression, verdict réévalué si disponible) :
  mots-clés et fichiers choisis PAR GEMMA (trace) + édition finale = patch final de Gemma en blocs SEARCH/REPLACE
  (tentatives ratées et retours d'oracle retirés : chemin condensé). Format EXACT de agent/flow.py / reconstruct.py.
Contrôle : les blocs appliqués sur le commit de base redonnent exactement l'état du patch final.
Aucune sortie de modèle propriétaire : seules les réponses de Gemma sont gardées, validées par exécution.

Refus : bugs du vivier TEST (étanchéité), bugs postérieurs à la coupure.
Usage : python3 trajectories/self_paths.py runs/<run_O_train> [...] [--cutoff 2025-06-01] [--out trajectories/self.jsonl]
"""
import argparse, csv, json, re, sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "agent"))
sys.path.insert(0, str(ROOT / "trajectories"))
import flow  # noqa: E402
from reconstruct import CODE, edit_text, hunks, make_blocks  # noqa: E402


def apply_diff(src, file_hunks):
    """Applique les hunks (touched, search, replace) d'un fichier : remplacement exact, dans l'ordre."""
    for _, s, r in file_hunks:
        if s not in src:
            return None
        src = src.replace(s, r, 1)
    return src


def enclosing_function(rows, i):
    """Nom de la fonction PHP/JS qui contient la ligne i (0-indexée), en remontant jusqu'à la déclaration."""
    for j in range(min(i, len(rows) - 1), -1, -1):
        m = re.search(r"\bfunction\s+&?(\w+)\s*\(", rows[j])
        if m:
            return m.group(1)
    return "(hors fonction)"


def similarity(d, pr):
    """Proximité avec le correctif officiel (0-1) : ratio difflib entre lignes ajoutées/retirées. 1.0 = identique.
    Sert de seuil de qualité à l'entraînement (ex. #37955 : correction partielle + clé de config inventée)."""
    import difflib
    ch = lambda t: "\n".join(l.strip() for l in t.splitlines() if l[:1] in "+-" and not l.startswith(("+++", "---")) and l[1:].strip())
    off = ROOT / "bench" / "diffs" / f"{pr}.diff"
    return round(difflib.SequenceMatcher(None, ch((d / "patch.diff").read_text()), ch(off.read_text())).ratio(), 2) if off.exists() else None


def verdict(d):
    r = json.loads((d / "result.json").read_text())
    for name in ("result_reeval2.json", "result_reeval.json"):
        if (d / name).exists():
            r.update({k: json.loads((d / name).read_text()).get(k) for k in ("applied", "fixed", "regression")})
            break
    return bool(r.get("fixed") and not r.get("regression"))


def trace_choices(d):
    """Mots-clés et fichiers choisis par Gemma : derniers choix faits avant l'édition finale."""
    kws, files = [], []
    for l in open(d / "trace.jsonl"):
        t = json.loads(l)
        a = t.get("assistant") or ""
        if t.get("step") == "localiser" or '"keywords"' in a:
            kws = flow.parse_json(a, "keywords") or kws
        if t.get("step") == "lire" or '"files"' in a:
            files = flow.parse_json(a, "files") or files
    return kws, files


def build(bug, d):
    base = bug["base_commit"]
    diff = (d / "patch.diff").read_text()
    hs = {f: h for f, h in hunks(diff).items() if f.endswith(CODE) and not f.startswith(flow.EXCLUDE)}
    if not hs or len(hs) > flow.MAX_FILES_READ:
        return None, "hors_format"
    # garde-fou anti-contournement (reward hacking) : un oracle écrit par le modèle peut être satisfait par un patch
    # symptomatique ailleurs (ex. #38417 : cas particulier ajouté dans ImageType au lieu de corriger l'appel fautif).
    # Sur TRAIN on connaît le correctif officiel → on n'accepte que des éditions DANS ses fichiers.
    official = set(bug["files"])
    if not set(hs) <= official:
        return None, "hors_fichiers_officiels"
    # … et au niveau FONCTION : #38168, patch dans le bon fichier mais dans une autre méthode (requête rendue vide)
    # → l'oracle passe, le code est cassé. On exige que chaque fonction éditée soit touchée par le correctif officiel.
    off_fns = {fn.split("::")[-1] for fns in bug["functions"].values() for fn in fns if not fn.startswith("(")}
    for f, fh in hs.items():
        src = flow.show(bug["base_commit"], f).splitlines()
        for touched, _, _ in fh:
            fn = enclosing_function(src, min(touched) if touched else 0)
            if off_fns and fn not in off_fns:
                return None, "hors_fonctions_officielles"
    kws, chosen = trace_choices(d)
    hits = flow.grep(base, kws)
    edited = list(hs)
    # chemin CONDENSÉ : on ne garde à l'étape LIRE que les fichiers réellement édités (les fichiers lus pour rien sont
    # retirés) ; si le chemin reste trop long, c'est le signe d'un contexte trop large et il est écarté ensuite.
    files = edited[:flow.MAX_FILES_READ]
    if any(f not in hits for f in edited):
        return None, "fichier_absent_recherche"
    blocks, contents = [], {}
    for f in files:
        src = flow.show(base, f)
        if not src:
            return None, "fichier_nouveau"
        touched = [i for t, _, _ in hs.get(f, []) for i in t]
        contents[f] = flow.windows(src, kws, extra_lines=touched)
        if f in hs:
            b = make_blocks(f, src, hs[f])
            if b is None:
                return None, "search_non_unique"
            blocks += b
    state, errors = flow.apply_edits(base, flow.parse_edits(edit_text(blocks)))
    if errors or any(state[f] != apply_diff(flow.show(base, f), hs[f]) for f in edited):
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
    ap.add_argument("runs", nargs="+")
    ap.add_argument("--cutoff", default="2025-06-01")
    ap.add_argument("--max-tokens", type=int, default=8000)
    ap.add_argument("--out", default=str(ROOT / "trajectories" / "self.jsonl"))
    ap.add_argument("--allow-test", action="store_true", help="contrôle de la mécanique uniquement (sortie hors trajectories/)")
    a = ap.parse_args()
    cat = {c["pr"]: c for c in map(json.loads, open(ROOT / "bench" / "catalog.jsonl"))}
    test = {int(r["pr"]) for r in csv.DictReader(open(ROOT / "data" / "bugs_test.csv"))}
    if a.allow_test and Path(a.out).resolve().is_relative_to(ROOT / "trajectories"):
        sys.exit("--allow-test : écrire hors de trajectories/ (jamais de TEST dans les données d'entraînement)")
    # étanchéité au niveau FONCTION (même règle que reconstruct.py) : aucun chemin touchant une fonction d'un bug post-coupure
    post = [c for c in cat.values() if (c["merged_at"] or "9999") >= a.cutoff]
    post_fns = {(f, fn) for c in post for f, fns in c["functions"].items() for fn in fns if not fn.startswith("(")}
    stats, out = {}, []
    for run in a.runs:
        for d in sorted(p for p in Path(run).iterdir() if p.is_dir() and p.name.isdigit() and (p / "result.json").exists()):
            pr = int(d.name)
            bug = cat.get(pr)
            if not bug:
                why = "hors_catalogue"
            elif not a.allow_test and (pr in test or (bug["merged_at"] or "9999") >= a.cutoff
                                       or {(f, fn) for f, fns in bug["functions"].items() for fn in fns} & post_fns):
                why = "exclu_etancheite"
            elif not verdict(d):
                why = "non_resolu"
            else:
                msgs, why = build(bug, d)
                if msgs:
                    tok = sum(len(m["content"]) for m in msgs) // 3
                    if tok > a.max_tokens:
                        why = "trop_long"
                    else:
                        out.append({"pr": pr, "issue": bug["issue"], "merged_at": bug["merged_at"], "source": "gemma_self",
                                    "run": Path(run).name, "tokens_est": tok, "similarite": similarity(d, pr), "messages": msgs})
            stats[why] = stats.get(why, 0) + 1
    with open(a.out, "w") as f:
        for o in out:
            f.write(json.dumps(o, ensure_ascii=False) + "\n")
    print(f"{len(out)} chemins → {a.out}  {stats}")


if __name__ == "__main__":
    main()
