"""Micro-index du code PrestaShop au commit de base (aide à la localisation, condition I).

Symboles : classes / interfaces / traits, méthodes (noms assez rares), hooks déclenchés, contrôleurs.
Construit par git grep sur le commit de base du bug (aucune fuite du correctif), mis en cache dans runs/_symbols/.
Aucun modèle.
"""
import gzip, json, re, subprocess
from collections import defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PS = ROOT / "bench" / "ps"
CACHE = ROOT / "runs" / "_symbols"
EXCLUDE = ("tests/", "vendor/", "translations/", "install-dev/", "js/", "admin-dev/themes/", ".github/", "var/")
MAX_METHOD_FILES = 4          # un nom de méthode défini dans plus de fichiers n'est pas discriminant
MAX_LINES = 12                # lignes d'index montrées à l'agent

PATTERNS = {
    "classe": r"^\s*(abstract\s+|final\s+|readonly\s+)*(class|interface|trait|enum)\s+[A-Za-z_]\w*",
    "méthode": r"function\s+[A-Za-z_]\w*\s*\(",
    "hook": r"(Hook::exec|dispatchHook|dispatchWithParameters|dispatch)\(\s*['\"][a-zA-Z]\w+['\"]",
}
NAME = {"classe": re.compile(r"(?:class|interface|trait|enum)\s+(\w+)"), "méthode": re.compile(r"function\s+(\w+)"),
        "hook": re.compile(r"\(\s*['\"](\w+)['\"]")}


def _grep(commit, pattern, exts=("php",)):
    r = subprocess.run(["git", "-C", str(PS), "grep", "-I", "-n", "-E", pattern, commit, "--", *(f"*.{e}" for e in exts)],
                       capture_output=True, text=True)
    for l in r.stdout.splitlines():
        parts = l.split(":", 3)  # commit:fichier:ligne:texte
        if len(parts) == 4 and not parts[1].startswith(EXCLUDE):
            yield parts[1], int(parts[2]), parts[3]


def build(commit):
    CACHE.mkdir(parents=True, exist_ok=True)
    p = CACHE / f"{commit[:12]}.json.gz"
    if p.exists():
        return json.loads(gzip.decompress(p.read_bytes()))
    idx = defaultdict(list)
    for kind, pat in PATTERNS.items():
        for f, n, text in _grep(commit, pat):
            m = NAME[kind].search(text)
            if m:
                idx[m.group(1).lower()].append([kind, f, n, m.group(1)])
    # méthodes trop répandues (get, __construct, init…) : retirées
    out = {}
    for k, v in idx.items():
        meth = [x for x in v if x[0] == "méthode"]
        if len({x[1] for x in meth}) > MAX_METHOD_FILES:
            v = [x for x in v if x[0] != "méthode"]
        if v:
            out[k] = v
    p.write_bytes(gzip.compress(json.dumps(out).encode()))
    return out


IDENT = re.compile(r"\b([A-Z][a-z0-9]+[A-Z]\w*|[a-z]+[A-Z]\w*|[A-Z]\w+(?=::)|(?<=::)\w+|(?:action|display)[A-Z]\w+)\b")


def identifiers(text):
    """Identifiants de code probables dans un texte (ticket) : CamelCase, camelCase, Classe::méthode, hooks."""
    seen = []
    for m in IDENT.findall(text):
        if len(m) >= 4 and m not in seen:
            seen.append(m)
    return seen


def lookup(commit, names):
    """Lignes « nom (type) → fichier:ligne » pour les noms présents dans l'index (ordre des noms)."""
    idx = build(commit)
    lines, seen = [], set()
    for n in names:
        for kind, f, line, orig in idx.get(n.strip().lower(), [])[:3]:
            key = (orig, f)
            if key not in seen:
                seen.add(key)
                lines.append(f"- {orig} ({kind}) → {f}:{line}")
    return lines[:MAX_LINES]
