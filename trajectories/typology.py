#!/usr/bin/env python3
"""Typologie des bugs d'entraînement (TRAIN uniquement) — déterministe, sans modèle.

Pour chaque exemple (trajectories/train.jsonl, trajectories/self.jsonl) : correctif officiel bench/diffs/<pr>.diff +
métadonnées bench/catalog.jsonl → étiquettes :
- couche(s) touchée(s) : legacy (classes/, controllers/), Symfony (src/Core, src/Adapter, src/PrestaShopBundle), templates,
  JS, SQL d'installation / mise à jour, modules natifs, configuration ;
- portée : nb de fichiers, nb de fonctions, « croisé » (legacy + Symfony dans le même correctif), taille ;
- nature (multi-étiquette, motifs sur les lignes ajoutées/retirées) : garde null/vide, condition, requête SQL, multiboutique,
  traduction, conversion/échappement, signature, hook, prix/taxe, cache, formulaire/validation, template.
Sorties : trajectories/typology.csv + docs/TYPOLOGIE.md (composition du jeu v16 et comparaison au vivier TRAIN complet).
Règle (REGLES.md) : on ne regarde PAS la typologie des bugs TEST pour composer l'entraînement.
Usage : python3 trajectories/typology.py
"""
import collections, csv, json, re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
CUTOFF = "2025-06-01"

LAYERS = [  # (étiquette, motif de chemin) — premier qui correspond
    ("legacy_classes", r"^classes/"), ("legacy_controllers", r"^controllers/"),
    ("symfony_core", r"^src/Core/"), ("symfony_adapter", r"^src/Adapter/"),
    ("symfony_bundle", r"^src/PrestaShopBundle/"), ("symfony_autre", r"^src/"),
    ("templates", r"\.(tpl|twig)$"), ("js", r"\.(js|ts|vue)$"), ("sql_install", r"^install(-dev)?/|\.sql$|upgrade/"),
    ("modules", r"^modules/"), ("admin_legacy", r"^admin(-dev)?/"), ("config", r"^(config|app)/"), ("autre", r"."),
]
NATURES = {
    "garde_null_vide": r"\b(isset|empty|is_null|null\s*[!=]==?|[!=]==?\s*null|\?\?|instanceof|Validate::isLoadedObject)\b",
    "condition": r"^\s*[+-].*\b(if|elseif|else|switch|case|&&|\|\|)\b",
    "requete_sql": r"\b(SELECT|JOIN|WHERE|GROUP BY|ORDER BY|DbQuery|->where\(|->leftJoin|->select\(|Db::getInstance|createQueryBuilder|executeS|getValue)\b",
    "multiboutique": r"\b(Shop::|id_shop|shop_group|ShopConstraint|isFeatureActive|getContextShop|Shop::CONTEXT|multistore|multishop)",
    "traduction": r"(->trans\(|->l\(|\$this->trans|translator|Translat|getTranslator|{l s=)",
    "conversion_echappement": r"(\(int\)|\(float\)|\(bool\)|intval\(|pSQL\(|bqSQL|htmlspecialchars|htmlentities|escape|Tools::safeOutput|json_encode|json_decode)",
    "hook": r"(Hook::exec|hookAction|dispatchHook|->dispatch\(|'action[A-Z]|\"action[A-Z])",
    "prix_taxe": r"\b(price|Price|tax|Tax|ps_round|Tools::round|discount|reduction|currency|Currency)\b",
    "cache": r"\b(Cache::|cache|Cache)\b",
    "formulaire_validation": r"(FormType|->add\(|Constraint|Assert\\|Validate::|validator|isValid|getErrors)",
}
SIG = re.compile(r"^[+-]\s*(public|protected|private|static|\s)*\s*function\s+\w+\s*\(")


def changed_lines(diff):
    return [l for l in diff.splitlines() if l[:1] in "+-" and not l.startswith(("+++", "---")) and l[1:].strip()]


def layer(path):
    return next(name for name, pat in LAYERS if re.search(pat, path))


def label(c):
    p = ROOT / "bench" / "diffs" / f"{c['pr']}.diff"
    diff = p.read_text(errors="replace") if p.exists() else ""
    lines = changed_lines(diff)
    files = c.get("files") or []
    layers = sorted({layer(f) for f in files})
    fns = sum(len([x for x in v if not x.startswith("(")]) for v in (c.get("functions") or {}).values())
    legacy = any(l.startswith(("legacy", "admin")) for l in layers)
    symfony = any(l.startswith("symfony") for l in layers)
    text = "\n".join(lines)
    natures = sorted(n for n, pat in NATURES.items() if re.search(pat, text, re.M))
    if any(SIG.match(l) for l in lines):
        natures.append("signature")
    if "templates" in layers:
        natures.append("template")
    n = len(lines)
    return {"pr": c["pr"], "merged_at": (c.get("merged_at") or "")[:10], "layers": "|".join(layers),
            "n_files": len(files), "n_functions": fns, "croise": legacy and symfony,
            "multi_fichiers": len(files) > 1, "taille": "≤5" if n <= 5 else "6-20" if n <= 20 else "21-60" if n <= 60 else ">60",
            "lignes": n, "natures": "|".join(sorted(set(natures))) or "autre"}


def table(title, counter, total):
    out = [f"### {title}", "", "| Étiquette | Exemples | Part |", "|---|---|---|"]
    out += [f"| {k} | {v} | {v / total:.0%} |" for k, v in counter.most_common()]
    return out + [""]


def compare(title, a, na, b, nb):
    keys = sorted(set(a) | set(b), key=lambda k: -(a.get(k, 0) / max(na, 1)))
    out = [f"### {title}", "", "| Étiquette | Jeu d'entraînement (592) | Vivier TRAIN (820) | Écart |", "|---|---|---|---|"]
    for k in keys:
        pa, pb = a.get(k, 0) / max(na, 1), b.get(k, 0) / max(nb, 1)
        out.append(f"| {k} | {pa:.0%} | {pb:.0%} | {(pa - pb) * 100:+.0f} pts |")
    return out + [""]


def main():
    cat = {c["pr"]: c for c in map(json.loads, open(ROOT / "bench" / "catalog.jsonl"))}
    test = {int(r["pr"]) for r in csv.DictReader(open(ROOT / "data" / "bugs_test.csv"))}
    ex = []
    for f in ("train.jsonl", "self.jsonl"):
        for l in open(ROOT / "trajectories" / f):
            o = json.loads(l)
            if o["pr"] in cat:
                ex.append({**label(cat[o["pr"]]), "source": o.get("source", "reconstruit"), "tokens_est": o.get("tokens_est")})
    assert not {e["pr"] for e in ex} & test, "un bug TEST est dans les données d'entraînement"
    with open(ROOT / "trajectories" / "typology.csv", "w", newline="") as fh:
        w = csv.DictWriter(fh, fieldnames=list(ex[0].keys())); w.writeheader(); w.writerows(ex)
    # vivier TRAIN complet (catalogue, avant la coupure) : référence de comparaison, pas de TEST
    pool = [label(c) for c in cat.values() if (c.get("merged_at") or "9999") < CUTOFF and c["pr"] not in test]
    cnt = lambda rows, key, multi=True: collections.Counter(x for r in rows for x in (str(r[key]).split("|") if multi else [str(r[key])]))
    n = len(ex)
    L = ["# Typologie des données d'entraînement (TRAIN uniquement)", "",
         "Généré par `python3 trajectories/typology.py` (déterministe, motifs sur le correctif officiel `bench/diffs/<pr>.diff`).",
         "Étiquettes multiples possibles (couches, natures) : les parts ne somment pas à 100 %.",
         "**Règle : la composition se décide d'après TRAIN seul — la typologie des bugs TEST n'est pas regardée.**", "",
         f"Exemples : **{n}** ({dict(collections.Counter(e['source'] for e in ex))}) ; vivier TRAIN de comparaison : {len(pool)} bugs du catalogue antérieurs au {CUTOFF}.", ""]
    L += table("Couches touchées", cnt(ex, "layers"), n)
    L += table("Natures du correctif", cnt(ex, "natures"), n)
    L += table("Taille (lignes modifiées)", cnt(ex, "taille", False), n)
    L += [f"- Correctifs **multi-fichiers** : {sum(e['multi_fichiers'] for e in ex)} ({sum(e['multi_fichiers'] for e in ex) / n:.0%}) ; "
          f"**croisés legacy + Symfony** : {sum(e['croise'] for e in ex)} ({sum(e['croise'] for e in ex) / n:.0%}).", ""]
    L += ["## Le jeu d'entraînement est-il représentatif du vivier TRAIN ?", ""]
    L += compare("Couches", cnt(ex, "layers"), n, cnt(pool, "layers"), len(pool))
    L += compare("Natures", cnt(ex, "natures"), n, cnt(pool, "natures"), len(pool))
    L += compare("Portée", collections.Counter({"multi_fichiers": sum(e["multi_fichiers"] for e in ex), "croise": sum(e["croise"] for e in ex)}), n,
                 collections.Counter({"multi_fichiers": sum(p["multi_fichiers"] for p in pool), "croise": sum(p["croise"] for p in pool)}), len(pool))
    L += ["## Lecture", "",
          "- Un écart fort (± 10 pts) signale une catégorie sur- ou sous-représentée par la construction des chemins",
          "  (`trajectories/reconstruct.py` : ≤ 3 fichiers lus, blocs SEARCH/REPLACE reproductibles, < 8 000 tokens).",
          "- L'ordre d'entraînement reste aléatoire (mélange à chaque époque) ; l'équilibrage se fait par la composition.", ""]
    (ROOT / "docs" / "TYPOLOGIE.md").write_text("\n".join(L))
    print("\n".join(L[:60]))


if __name__ == "__main__":
    main()
