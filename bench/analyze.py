#!/usr/bin/env python3
"""Catalogue déterministe des bugs candidats : ticket, résolution, fonctions touchées, date de merge.

Usage : python3 bench/analyze.py [--in bugs_all.jsonl]
Sortie : bench/catalog.jsonl (1 ligne par bug) + docs/CATALOGUE.md (statistiques)
Aucune génération par modèle : extraction par regex/git uniquement.
"""
import argparse, collections, json, re, subprocess
from pathlib import Path

HERE = Path(__file__).parent
PS = HERE / "ps"
REPO = "PrestaShop/PrestaShop"
FUNC = re.compile(r"^\s*(?:(?:abstract|final|public|protected|private|static)\s+)*function\s+&?\s*(\w+)\s*\(")
CLASS = re.compile(r"^\s*(?:abstract\s+|final\s+)?(?:class|trait|interface|enum)\s+(\w+)")
JSFUNC = re.compile(r"^\s*(?:async\s+)?(?:function\s+(\w+)|(\w+)\s*\([^)]*\)\s*\{|(?:const|let|var)\s+(\w+)\s*=\s*(?:async\s*)?\()")


def git(*a):
    r = subprocess.run(["git", "-C", str(PS), *a], capture_output=True, text=True)
    return r.stdout if r.returncode == 0 else None


def section(body, *names):
    """Contenu d'une section markdown '### Nom' du template d'issue."""
    for n in names:
        m = re.search(rf"#+\s*{n}[^\n]*\n(.*?)(?=\n#+\s|\Z)", body, re.S | re.I)
        if m:
            t = m.group(1).strip()
            if t and t != "_No response_":
                return t
    return ""


def pr_row(body, key):
    m = re.search(rf"\|\s*{key}\??\s*\|\s*(.*)", body or "", re.I)
    return re.sub(r"<br\s*/?>", " ", m.group(1)).strip() if m else ""


def changed_old_lines(diff):
    """{fichier: [lignes de l'ancien fichier touchées]} (ajouts rattachés à la ligne précédente)."""
    out, f, old = collections.defaultdict(list), None, 0
    for l in diff.splitlines():
        if l.startswith("--- "):
            continue
        if l.startswith("+++ "):
            f = l[6:] if l.startswith("+++ b/") else None
        elif l.startswith("@@"):
            old = int(re.match(r"@@ -(\d+)", l).group(1))
        elif f is None:
            continue
        elif l.startswith("-"):
            out[f].append(old); old += 1
        elif l.startswith("+"):
            out[f].append(max(old - 1, 1))
        else:
            old += 1
    return out


def enclosing(src, lines, js=False):
    """Pour chaque ligne, classe::fonction englobante (scan arrière)."""
    rows = src.splitlines()
    res = []
    for n in sorted(set(lines)):
        fn = cls = None
        for i in range(min(n, len(rows)) - 1, -1, -1):
            if fn is None:
                m = (JSFUNC if js else FUNC).match(rows[i])
                if m:
                    fn = next(g for g in m.groups() if g) if js else m.group(1)
                    if fn in ("if", "for", "while", "switch", "catch"):
                        fn = None
            if not js and cls is None:
                m = CLASS.match(rows[i])
                if m:
                    cls = m.group(1); break
        name = f"{cls}::{fn}" if cls and fn else (fn or cls or "(hors fonction)")
        if name not in res:
            res.append(name)
    return res


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--in", dest="inp", nargs="+", default=["bugs_all.jsonl"])
    a = ap.parse_args()
    seen, bugs = set(), []
    for fn in a.inp:
        for l in open(HERE / fn):
            b = json.loads(l)
            if b["pr"] not in seen:
                seen.add(b["pr"]); bugs.append(b)
    meta = {p["number"]: p for p in json.loads(subprocess.run(
        ["gh", "pr", "list", "-R", REPO, "--state", "merged", "--label", "Bug fix", "--limit", "1000",
         "--json", "number,body,mergedAt,author"], check=True, capture_output=True, text=True).stdout)}

    cat = []
    for b in bugs:
        m = meta.get(b["pr"])
        if m is None:  # hors des 1000 dernières : requête individuelle
            m = json.loads(subprocess.run(["gh", "pr", "view", str(b["pr"]), "-R", REPO, "--json", "number,body,mergedAt,author"],
                                          capture_output=True, text=True).stdout or "{}")
        diff = (HERE / "diffs" / f"{b['pr']}.diff").read_text()
        funcs = {}
        for f, ls in changed_old_lines(diff).items():
            ext = f.rsplit(".", 1)[-1]
            if ext not in ("php", "js", "ts", "vue"):
                funcs[f] = [f"({ext})"]
                continue
            src = git("show", f"{b['base_commit']}:{f}") or ""
            funcs[f] = enclosing(src, ls, js=ext != "php") if src else ["(nouveau fichier)"]
        ib = b["issue_body"]
        cat.append({
            "pr": b["pr"], "issue": b["issue"], "branch": b["branch"], "merged_at": m.get("mergedAt"),
            "area": b["area"], "lines": b["lines"], "files": b["files"], "functions": funcs,
            "ticket": {
                "title": b["issue_title"],
                "bug": section(ib, "Describe the bug", "Description"),
                "expected": section(ib, "Expected behavior"),
                "steps": section(ib, "Steps to reproduce", "How to reproduce"),
                "versions": section(ib, r"PrestaShop version"),
            },
            "resolution": {
                "title": b["title"],
                "description": pr_row(m.get("body"), "Description"),
                "how_to_test": pr_row(m.get("body"), "How to test"),
                "category": pr_row(m.get("body"), "Category"),
                "author": (m.get("author") or {}).get("login"),
            },
            "base_commit": b["base_commit"], "merge_commit": b["merge_commit"], "url": b["url"],
        })
    with open(HERE / "catalog.jsonl", "w") as f:
        for c in cat:
            f.write(json.dumps(c, ensure_ascii=False) + "\n")

    # Statistiques
    C = collections.Counter
    months = C((c["merged_at"] or "?")[:7] for c in cat)
    top_dirs = C(p.split("/")[0] + ("/" + p.split("/")[1] if p.count("/") > 1 else "") for c in cat for p in c["files"])
    top_cls = C(fn.split("::")[0] for c in cat for fs in c["functions"].values() for fn in fs if "::" in fn)
    cats = C(c["resolution"]["category"] or "?" for c in cat)
    has_steps = sum(1 for c in cat if c["ticket"]["steps"])
    md = ["# Catalogue des bugs candidats", "",
          f"Généré par `bench/analyze.py` (extraction déterministe, aucun modèle). {len(cat)} bugs, "
          f"dont {has_steps} avec étapes de repro. Données : `bench/catalog.jsonl`.", "",
          "## Par branche", "", "| Branche | Bugs |", "|---|---|",
          *[f"| {k} | {v} |" for k, v in C(c['branch'] for c in cat).most_common()], "",
          "## Par mois de merge", "", "| Mois | Bugs |", "|---|---|",
          *[f"| {k} | {v} |" for k, v in sorted(months.items())], "",
          "## Catégorie (template de PR)", "", "| Cat. | Bugs |", "|---|---|",
          *[f"| {k[:30]} | {v} |" for k, v in cats.most_common(12)], "",
          "## Dossiers les plus touchés", "", "| Dossier | Fichiers |", "|---|---|",
          *[f"| `{k}` | {v} |" for k, v in top_dirs.most_common(15)], "",
          "## Classes les plus touchées", "", "| Classe | Modifs |", "|---|---|",
          *[f"| `{k}` | {v} |" for k, v in top_cls.most_common(20)], ""]
    (HERE.parent / "docs" / "CATALOGUE.md").write_text("\n".join(md))
    print(f"{len(cat)} bugs → catalog.jsonl ; mois : {min(months)} … {max(months)}")


if __name__ == "__main__":
    main()
