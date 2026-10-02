"""Condition R : « fine-tuning simulé » par injection de contexte (few-shot par récupération).

Pour un bug, on retrouve les k chemins de correction TRAIN les plus proches (TF-IDF sur le ticket, sans modèle)
et on les injecte en exemples condensés dans le prompt. Étanchéité : l'index ne contient que trajectories/train.jsonl
(vivier TRAIN, avant la coupure) ; même PR ou même issue exclues.
"""
import json, math, re
from collections import Counter
from functools import lru_cache
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
TOK = re.compile(r"[A-Za-zÀ-ÿ_][A-Za-zÀ-ÿ0-9_]{2,}")
STOP = set("the and for with that this when not are from you your have has but can should into then there its after before "
           "les des une pour avec dans sur est pas que qui ticket attendu étapes describe bug expected steps reproduce "
           "prestashop version php issue".split())


def toks(text):
    out = []
    for t in TOK.findall(text):
        # CamelCase → mots (StockController → stock, controller)
        parts = re.findall(r"[A-Z]?[a-zà-ÿ]+|[A-Z]+(?![a-z])|\d+", t) or [t]
        out += [p.lower() for p in parts if len(p) > 2]
    return [t for t in out if t not in STOP]


@lru_cache(maxsize=1)
def index():
    rows = [json.loads(l) for l in open(ROOT / "trajectories" / "train.jsonl")]
    docs = []
    for r in rows:
        ticket = r["messages"][1]["content"].split("ÉTAPE 1")[0]
        docs.append((r, Counter(toks(ticket))))
    df = Counter(t for _, c in docs for t in c)
    n = len(docs)
    idf = {t: math.log((n + 1) / (d + 1)) + 1 for t, d in df.items()}
    vecs = []
    for r, c in docs:
        v = {t: tf * idf[t] for t, tf in c.items()}
        norm = math.sqrt(sum(x * x for x in v.values())) or 1
        vecs.append((r, v, norm))
    return vecs, idf


def similar(bug, k=2):
    vecs, idf = index()
    q = Counter(toks(" ".join([bug["ticket"]["title"], bug["ticket"].get("bug", ""), bug["ticket"].get("steps", "")])))
    qv = {t: tf * idf.get(t, 1) for t, tf in q.items()}
    qn = math.sqrt(sum(x * x for x in qv.values())) or 1
    scored = []
    for r, v, n in vecs:
        if r["pr"] == bug["pr"] or (r.get("issue") and r.get("issue") == bug.get("issue")):
            continue
        s = sum(qv[t] * v[t] for t in qv if t in v) / (qn * n)
        scored.append((s, r))
    scored.sort(key=lambda x: -x[0])
    return scored[:k]


def render(bug, k=2, max_edit=1500):
    """Bloc texte injecté dans le 1er message (condition R)."""
    out = []
    for score, r in similar(bug, k):
        m = r["messages"]
        title = re.search(r"TICKET\n# (.*)", m[1]["content"])
        out.append(f"--- Exemple (bug similaire déjà corrigé, PR #{r['pr']}, similarité {score:.2f})\n"
                   f"Ticket : {title.group(1) if title else '?'}\n"
                   f"Étape 1 : {m[2]['content']}\nÉtape 2 : {m[4]['content']}\n"
                   f"Étape 3 :\n{m[6]['content'][:max_edit]}")
    return "\n\n".join(out)
