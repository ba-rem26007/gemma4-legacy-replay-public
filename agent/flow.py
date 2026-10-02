"""Déroulé FIXE de l'agent (localiser → lire → éditer → tester) et outils déterministes.

Partagé par agent/run.py (évaluation, Gemma génère les tours assistant) et
trajectories/reconstruct.py (entraînement, tours assistant reconstruits depuis le correctif officiel).
Le format des messages est donc STRICTEMENT identique en évaluation et en entraînement.
"""
import csv, json, os, re, subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PS = ROOT / "bench" / "ps"
EXCLUDE = ("tests/", "vendor/", "translations/", "install-dev/", "js/tiny_mce", "admin-dev/themes/default/public", ".github/")
CODE_EXT = ("php", "tpl", "twig", "js", "ts", "vue")
MAX_FILES_READ, WINDOW, MAX_LINES_PER_FILE, MAX_GREP_FILES = 3, 20, int(os.environ.get("MAX_LINES_PER_FILE", 120)), 25

SYSTEM = """Tu es un agent qui corrige des bugs dans PrestaShop (PHP, legacy + Symfony).
Tu suis un déroulé FIXE en étapes. À chaque étape, réfléchis brièvement puis réponds UNIQUEMENT dans le format demandé, sans explication.
1. LOCALISER : proposer des mots-clés de recherche (noms de classes, méthodes, variables, clés de traduction).
2. LIRE : choisir au plus 3 fichiers à lire parmi les résultats de recherche.
3. ÉDITER : produire des blocs SEARCH/REPLACE. Le texte SEARCH doit être copié EXACTEMENT depuis le fichier lu.
4. TESTER : si un test échoue, corriger avec de nouveaux blocs SEARCH/REPLACE."""

EDIT_FORMAT = """Format (un ou plusieurs blocs) :
FILE: chemin/relatif.php
<<<<<<< SEARCH
lignes exactes existantes
=======
lignes corrigées
>>>>>>> REPLACE"""


# ---------------------------------------------------------------- outils (déterministes)
def git(*a):
    r = subprocess.run(["git", "-C", str(PS), *a], capture_output=True, text=True)
    return r.stdout if r.returncode == 0 else ""


def show(commit, path):
    return git("show", f"{commit}:{path}")


def grep(commit, keywords):
    """Recherche sur le commit de base : contenu (git grep) + chemins de fichiers.
    Classement : nom de fichier contenant un mot-clé, puis nb de mots-clés DISTINCTS trouvés, puis nb de lignes."""
    kws = [k.strip() for k in keywords[:8] if len(k.strip()) >= 3]
    if not kws:
        return {}
    kl = [k.lower() for k in kws]
    found, lines = {}, {}
    pats = [x for k in kws for x in ("-e", k)]
    out = git("grep", "-I", "-o", "-i", "-F", *pats, commit, "--", *(f"*.{e}" for e in CODE_EXT))
    for l in out.splitlines():
        parts = l.split(":", 2)  # commit:fichier:correspondance (-o, sans -n)
        if len(parts) < 3 or parts[1].startswith(EXCLUDE):
            continue
        f, m = parts[1], parts[2].lower()
        found.setdefault(f, set()).add(m)
        lines[f] = lines.get(f, 0) + 1
    # recherche par chemin : seulement pour les mots-clés PRÉCIS (CamelCase, chemin, snake_case, long)
    specific = [k.lower() for k in kws if re.search(r"[A-Z/_.]", k[1:]) or len(k) >= 12]
    for f in git("ls-tree", "-r", "--name-only", commit).splitlines():
        if f.endswith(tuple(CODE_EXT)) and not f.startswith(EXCLUDE):
            fl = f.lower()
            for k in specific:
                if k in fl:
                    found.setdefault(f, set()).add(k)
                    lines.setdefault(f, 0)
    name_hit = lambda f: any(k in f.rsplit("/", 1)[-1].lower() or ("/" in k and k in f.lower()) for k in specific)
    ranked = sorted(found, key=lambda f: (not name_hit(f), -len(found[f]), -lines[f]))[:MAX_GREP_FILES]
    return {f: lines[f] for f in ranked}


def windows(src, keywords, extra_lines=()):
    """Extraits du fichier classés par pertinence (densité de symboles/mots-clés) plutôt que position séquentielle.
    
    Évite de saturer le budget de lecture sur du boilerplate d'en-tête (licences, use) 
    quand la méthode fautive se trouve en fin de fichier.
    """
    rows = src.splitlines()
    if not rows:
        return ""
    kws = [k.strip().lower() for k in keywords if len(k.strip()) >= 3]
    
    # 1. Notation de chaque ligne selon la pertinence des symboles
    marks = {}
    for i, l in enumerate(rows):
        ll = l.lower()
        score = 0
        for k in kws:
            if k in ll:
                score += 3 if re.search(r"\b" + re.escape(k) + r"\b", ll) else 1
        if i in extra_lines:
            score += 6
        if score > 0:
            marks[i] = score

    if not marks:
        return "\n".join(rows[:MAX_LINES_PER_FILE])

    # 2. Construction et fusion des spans
    raw_spans = []
    for i, sc in sorted(marks.items()):
        a, b = max(0, i - WINDOW), min(len(rows), i + WINDOW + 1)
        raw_spans.append({"a": a, "b": b, "score": sc})

    merged = []
    for sp in sorted(raw_spans, key=lambda s: s["a"]):
        if merged and sp["a"] <= merged[-1]["b"]:
            merged[-1]["b"] = max(merged[-1]["b"], sp["b"])
            merged[-1]["score"] += sp["score"]
        else:
            merged.append(sp)

    # 3. Classement des spans par densité de pertinence
    ranked_spans = sorted(merged, key=lambda s: -s["score"])

    # 4. Sélection des spans les plus denses dans la limite de MAX_LINES_PER_FILE
    selected, total = [], 0
    for sp in ranked_spans:
        length = sp["b"] - sp["a"]
        if total + length <= MAX_LINES_PER_FILE or not selected:
            selected.append(sp)
            total += length
        if total >= MAX_LINES_PER_FILE:
            break

    # 5. Restitution dans l'ordre chronologique des lignes du fichier
    selected.sort(key=lambda s: s["a"])
    out = []
    for sp in selected:
        out.append(f"[lignes {sp['a'] + 1}-{sp['b']}]\n" + "\n".join(rows[sp["a"]:sp["b"]]))

    return "\n[...]\n".join(out) if out else "\n".join(rows[:MAX_LINES_PER_FILE])


def glossary_hits(ticket_text):
    """Condition C : entrées du glossaire métier dont le terme ou un synonyme apparaît dans le ticket."""
    # GLOSSAIRE=glossaire_auto.csv → glossaire extrait automatiquement (provisoire, avant relecture de Rémi)
    import os
    # plusieurs fichiers possibles : GLOSSAIRE=glossaire_auto.csv,pages.csv (pages.csv = index des pages BO, glossaire/pages.py)
    paths = [ROOT / "glossaire" / n for n in os.environ.get("GLOSSAIRE", "glossaire.csv").split(",")]
    t = ticket_text.lower()
    lines = []
    for r in (r for p in paths if p.exists() for r in csv.DictReader(open(p))):
        names = [r["terme"]] + [s for s in r["synonymes"].split("|") if s]
        if any(n.lower() in t for n in names):
            syn = f" ({', '.join(names[1:4])})" if len(names) > 1 else ""
            lines.append(f"- {r['terme']}{syn} → {r['symboles'].replace('|', ', ')}")
    return "\n".join(lines)


# ---------------------------------------------------------------- messages (format figé)
def ticket_text(bug):
    t = bug["ticket"]
    parts = [f"# {t['title']}", t.get("bug", ""), "## Attendu\n" + t["expected"] if t.get("expected") else "",
             "## Étapes\n" + t["steps"] if t.get("steps") else ""]
    return "\n\n".join(p for p in parts if p).strip()[:4000]


def msg_ticket(bug, condition, replay_spec=""):
    s = f"TICKET\n{ticket_text(bug)}\n"
    if condition in ("B", "C", "D", "E", "R") and replay_spec:
        s += f"\nTEST DE REJEU (doit passer après correction)\n```js\n{replay_spec[:3000]}\n```\n"
    if condition in ("C", "D", "E", "R"):
        g = glossary_hits(ticket_text(bug))
        if g:
            s += f"\nCONTEXTE PRESTASHOP (vocabulaire métier → code)\n{g}\n"
    if condition == "E" or os.environ.get("USE_RULES", "0") == "1":
        import rules_prestashop
        s += rules_prestashop.get_rules_prompt()
    if condition == "R":  # fine-tuning simulé : exemples de corrections similaires (vivier TRAIN)
        import fewshot
        ex = fewshot.render(bug)
        if ex:
            s += f"\nEXEMPLES DE CORRECTIONS SIMILAIRES (même déroulé, pour t'inspirer)\n{ex}\n"
    s += '\nÉTAPE 1 LOCALISER. Réponds en JSON : {"keywords": ["...", "..."]} (3 à 8 mots-clés).'
    return s


def msg_grep(hits):
    lst = "\n".join(f"{f} ({n})" for f, n in hits.items()) or "(aucun résultat)"
    return f'RÉSULTATS DE RECHERCHE (fichier, nb de lignes)\n{lst}\n\nÉTAPE 2 LIRE. Réponds en JSON : {{"files": ["..."]}} (1 à 3 fichiers).'


BACKTRACK = ('Si ces fichiers ne sont pas les bons, tu peux à la place répondre {"files": [...]} pour lire '
             'd\'autres fichiers, ou {"keywords": [...]} pour relancer une recherche (2 fois au plus).')


def msg_read(contents):
    body = "\n\n".join(f"===== {f} =====\n{c}" for f, c in contents.items())
    return f"CONTENU\n{body}\n\nÉTAPE 3 ÉDITER.\n{EDIT_FORMAT}\n{BACKTRACK}"


def msg_test(result, state=None, commit=None):
    if result.get("fixed"):
        return "RÉSULTAT DU TEST : OK."
    err = result.get("replay_error") or "patch non applicable"
    cur = ""
    if state and commit:
        # état ACTUEL des fichiers déjà modifiés : les blocs SEARCH suivants doivent être copiés depuis CE texte
        # (sinon « bloc SEARCH introuvable » en boucle après une première édition, cf. #38341)
        import difflib
        parts = []
        for f, new in state.items():
            old = show(commit, f).splitlines()
            rows = new.splitlines()
            changed = [j for tag, i1, i2, j1, j2 in difflib.SequenceMatcher(None, old, rows).get_opcodes()
                       if tag != "equal" for j in range(j1, max(j2, j1 + 1))]
            parts.append(f"===== {f} (état actuel, après tes éditions) =====\n{windows(new, [], extra_lines=changed)}")
        cur = "\n\nFICHIERS MODIFIÉS — copie tes blocs SEARCH depuis CE texte :\n" + "\n\n".join(parts)
    return f"RÉSULTAT DU TEST : ÉCHEC\n{err}{cur}\n\nÉTAPE 4 CORRIGER.\n{EDIT_FORMAT}\n{BACKTRACK}"


# ---------------------------------------------------------------- parsing / application des éditions
def parse_json(text, key):
    m = re.search(r"\{.*\}", text, re.S)
    try:
        v = json.loads(m.group(0))[key] if m else []
        return [str(x) for x in v] if isinstance(v, list) else []
    except Exception:
        return re.findall(r'"([^"]{3,80})"', text)[:8]


BLOCK = re.compile(r"FILE:\s*(\S+)\s*\n<<<<<<< SEARCH\n(.*?)\n=======\n(.*?)\n?>>>>>>> REPLACE", re.S)


def parse_edits(text):
    return [(f.strip("`"), s, r) for f, s, r in BLOCK.findall(text)]


def apply_edits(commit, edits, files_state=None):
    """Applique les blocs sur l'état courant des fichiers → (nouvel état, erreurs)."""
    state = dict(files_state or {})
    errors = []
    for f, s, r in edits:
        src = state.get(f) if f in state else show(commit, f)
        if not src:
            errors.append(f"{f} : fichier introuvable"); continue
        if s not in src:
            errors.append(f"{f} : bloc SEARCH introuvable (copie exacte requise)"); continue
        state[f] = src.replace(s, r, 1)
    return state, errors


def to_diff(commit, state):
    """État modifié → diff unifié (a/ b/) applicable par patch -p1."""
    import difflib
    out = []
    for f, new in state.items():
        old = show(commit, f)
        d = difflib.unified_diff(old.splitlines(True), new.splitlines(True), f"a/{f}", f"b/{f}")
        out.append("".join(d))
    return "".join(out)
