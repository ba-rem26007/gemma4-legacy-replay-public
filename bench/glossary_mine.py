#!/usr/bin/env python3
"""Candidats glossaire : cooccurrence mots du ticket ↔ symboles touchés par le correctif officiel.

Usage : python3 bench/glossary_mine.py [--before 2025-01-01] [--min-support 3] [--gitlog fichier]
  --gitlog : sortie de `git log --no-merges --before=<coupure> --name-only --format='@@%s'`
             → cooccurrence message de commit ↔ classe (nom de fichier), beaucoup plus de signal
  --before : ne garder que les bugs mergés AVANT cette date (vivier TRAIN, étanchéité)
Sortie : glossaire/candidats_mine.csv (à relire par Rémi ; rien n'entre dans glossaire.csv sans validation)
Déterministe, aucun modèle.
"""
import argparse, collections, csv, json, math, re, unicodedata
from pathlib import Path

HERE = Path(__file__).parent
STOP = set("""the a an and or of to in on for with is are be it this that when if not no as at by from
can cannot does do done was were has have had you your we our i my me they them there then than but so
into out up all any some one two only also more very same other after before while should would could
will just get got see seen still even like using used use new old page button click go try want need
le la les un une des et ou de du en sur pour avec est sont pas ne que qui dans au aux ce cette il elle
on se sa son ses leur plus bug issue error prestashop version expected behavior steps reproduce
describe shop 8 9 1 2 3 x 0 ps php https http www com github fix fixed fixes add added adding update
updated remove removed improve refacto refactor merge wip test tests typo cs clean minor change changes
missing wrong bad better allow make set rename move moved revert don't doesn't isn't""".split())
WORD = re.compile(r"[a-zA-Zàâäéèêëîïôöùûüç][a-zA-Zàâäéèêëîïôöùûüç'_-]{2,}")


def norm(w):
    w = unicodedata.normalize("NFKD", w.lower()).encode("ascii", "ignore").decode()
    return w.strip("'-_")


def terms(text):
    ws = [norm(w) for w in WORD.findall(text)]
    ws = [w for w in ws if w and w not in STOP and not w.isdigit()]
    out = set(ws)
    out |= {f"{a} {b}" for a, b in zip(ws, ws[1:])}
    return out


def symbols(c):
    s = set()
    for f, fns in c["functions"].items():
        for fn in fns:
            if fn.startswith("("):
                continue
            cls = fn.split("::")[0]
            s.add(re.sub(r"Core$", "", cls))
            if "::" in fn:
                s.add(re.sub(r"Core::", "::", fn))
    return s


def path_symbol(p):
    """Classe déduite du chemin de fichier (PrestaShop : 1 classe par fichier)."""
    if p.startswith(("tests/", "vendor/", "install-dev/upgrade/", "translations/")):
        return None
    base = p.rsplit("/", 1)[-1].split(".")[0]
    if not base or not base[0].isupper() or base in ("index",):
        return None
    return re.sub(r"Core$", "", base)


def from_gitlog(path):
    subj, files = None, set()
    for l in open(path, errors="ignore"):
        l = l.rstrip("\n")
        if l.startswith("@@"):
            if subj is not None:
                yield subj, files
            subj, files = l[2:], set()
        elif l:
            s = path_symbol(l)
            if s:
                files.add(s)
    if subj is not None:
        yield subj, files


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--before", default=None)
    ap.add_argument("--min-support", type=int, default=3)
    ap.add_argument("--gitlog", default=None)
    a = ap.parse_args()
    if a.gitlog:
        docs = [(subj[:60], terms(subj), s) for subj, s in from_gitlog(a.gitlog) if 0 < len(s) <= 8]
    else:
        cat = [json.loads(l) for l in open(HERE / "catalog.jsonl")]
        if a.before:
            cat = [c for c in cat if (c["merged_at"] or "9999") < a.before]
        docs = [(c["pr"], terms(" ".join([c["ticket"]["title"], c["ticket"]["bug"], c["ticket"]["steps"]])), symbols(c)) for c in cat]
    N = len(docs)
    tdf, sdf, co, ex = collections.Counter(), collections.Counter(), collections.Counter(), collections.defaultdict(list)
    for ref, t, s in docs:
        tdf.update(t); sdf.update(s)
        for x in t:
            for y in s:
                co[x, y] += 1
                if len(ex[x, y]) < 5:
                    ex[x, y].append(ref)
    rows = []
    for (t, s), n in co.items():
        if n < a.min_support or tdf[t] > N * 0.3:
            continue
        pmi = math.log2(n * N / (tdf[t] * sdf[s]))
        conf = n / tdf[t]  # P(symbole | terme dans le ticket)
        if pmi < 1.5:
            continue
        rows.append((t, s, n, tdf[t], sdf[s], round(pmi, 2), round(conf, 2), " || ".join(map(str, ex[t, s][:3]))))
    # meilleur symbole par terme : on garde les 3 premiers par confiance
    rows.sort(key=lambda r: (r[0], -r[6], -r[5]))
    keep, cnt = [], collections.Counter()
    for r in rows:
        if cnt[r[0]] < 3:
            keep.append(r); cnt[r[0]] += 1
    rows = sorted(keep, key=lambda r: (-r[2] * r[6], -r[5]))
    out = HERE.parent / "glossaire" / ("candidats_gitlog.csv" if a.gitlog else "candidats_mine.csv")
    with open(out, "w", newline="") as f:
        w = csv.writer(f)
        w.writerow(["terme_ticket", "symbole", "cooc", "df_terme", "df_symbole", "pmi", "confiance", "exemples_pr"])
        w.writerows(rows)
    print(f"{N} documents ({'gitlog' if a.gitlog else 'bugs'}, avant {a.before or 'coupure du gitlog'}) → {len(rows)} paires candidates → {out}")


if __name__ == "__main__":
    main()
