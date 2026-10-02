#!/usr/bin/env python3
"""Gemma ÉCRIT l'oracle d'un bug (ticket + correctif officiel), validé automatiquement pre/post.

But : produire des milliers de tests (vivier TRAIN 1.6/1.7/8.x) sans agent Claude.
Le test n'est gardé que s'il ÉCHOUE sur le code d'avant et PASSE sur le correctif officiel.
En cas d'échec de validation, l'erreur est renvoyée à Gemma (2 corrections max).
Les tests générés sont des VÉRIFICATEURS, jamais des données d'entraînement (pas de trajectoire).

Usage : PSB=5 python3 bench/gentest.py <pr> [<pr>…] [--model gemma-4-31b-it] [--tries 3]
Sortie : bench/replay/g<pr>/ (oracle_gemma*.spec.js, setup.sql, STATUS) ; résumé bench/gentest.jsonl
"""
import argparse, json, os, re, subprocess, sys, time
from pathlib import Path

B = Path(__file__).resolve().parent
ROOT = B.parent
sys.path.insert(0, str(ROOT / "agent"))
import run as agentrun  # chat() (limiteur de débit, budget), load_env()
import flow

PSB = os.environ.get("PSB", "1")
EXAMPLES = {"bo": 40651, "fo": 41036}  # oracles validés à la main, sur d'AUTRES bugs (jamais celui traité)

ENV_NOTES = """ENVIRONNEMENT DE TEST (PrestaShop en Docker, données de démo FR installées)
- Playwright (JS, CommonJS) : `const { test, expect } = require('@playwright/test');`. baseURL déjà configurée.
- Front : '/', '/index.php?id_product=1&controller=product', '/index.php?controller=category&id_category=3', etc.
  Client démo : pub@prestashop.com / 123456789. Produits 1..19, commandes 1..5, thème des images 9.x = hummingbird.
- Back-office : fichier `oracle_gemma.bo.spec.js` → session admin DÉJÀ ouverte. Aller sur page.goto('/admin-dev/'),
  puis récupérer les liens du menu (les URL Symfony exigent un _token). Un lien sans jeton mène à une page de sécurité :
  cliquer le texte /comprends les risques|understand the risks/i.
- Base MySQL (préfixe ps_) accessible dans le test :
  const { execSync } = require('child_process');
  const PROJ = 'psbench' + ((process.env.PS_PORT||'8081')==='8081' ? '' : String(Number(process.env.PS_PORT)-8080));
  const sql = q => execSync(`docker exec ${PROJ}-db-1 mysql -padmin prestashop -N -e "${q}"`).toString();
- setup.sql : exécuté AVANT chaque run (base remise à zéro juste avant) ; doit être idempotent.
- Boutique en mode production (erreurs PHP non affichées) ; aucune règle de taxe installée.
- Le test doit vérifier le comportement CORRIGÉ : il doit ÉCHOUER sur le code d'avant le correctif et PASSER après.
  Vise une assertion métier précise (valeur, texte, statut HTTP, ligne en base), pas un simple chargement de page.
- ROBUSTESSE : boutique en FRANÇAIS. Ne devine JAMAIS un libellé exact (titres, messages) : préfère le statut HTTP,
  la présence/absence d'un élément, une valeur numérique, un montant, une ligne en base (sql(...)), ou une regex large.
  Une seule assertion décisive vaut mieux que plusieurs assertions fragiles."""

FORMAT = """RÉPONDS EXACTEMENT dans ce format (rien d'autre) :
KIND: fo|bo
```sql
-- contenu de setup.sql (ou vide)
```
```js
// contenu du test Playwright
```"""


ENV_PHP = """ORACLE PHP (exécuté en ligne de commande DANS le conteneur PrestaShop, depuis /var/www/html)
- Commence par : <?php require 'config/config.inc.php';  (charge PrestaShop : classes legacy, Db, Context, conteneur Symfony)
- Context::getContext() : boutique 1, langue 1 (fr), aucun client/employé connecté (crée-les si besoin, ex. new Employee(1)).
- Données de démo : produits 1..19, clients 1..2, commandes 1..5, catégories 2..9. Base MySQL préfixe ps_ (Db::getInstance()).
- Le conteneur Symfony N'EST PAS disponible en ligne de commande : instancie DIRECTEMENT les classes de src/ avec new
  (use Namespace\\Complet\\Classe;) en leur passant leurs dépendances (souvent des objets legacy : Cart, Context…).
- N'utilise QUE des classes et méthodes qui existent : celles du correctif, du code montré, et les classes legacy
  courantes (Cart, Order, Product, Customer, Context, Db, Tools, Configuration). Ne devine jamais un nom de classe.
- RÉUTILISE D'ABORD les données de démo existantes (new Order(1), new Cart(1), new Customer(1), new Product(1)…) :
  créer une commande ou un panier complet est long (nombreux champs requis). Ne crée que ce qui manque.
- PAS de SQL à part : crée les données nécessaires DANS le script avec les classes PrestaShop
  (ex. $c = new Cart(); $c->id_currency = 1; $c->id_lang = 1; $c->add(); puis $c->updateQty(1, 1);),
  elles remplissent les champs par défaut (dates…). La base est remise à zéro avant chaque exécution.
- Appelle DIRECTEMENT le code touché par le correctif (méthode, validateur, requête) avec des entrées qui déclenchent le bug.
- exit(0) si le comportement est CORRIGÉ, exit(1) sinon ; affiche (echo) les valeurs observées pour le diagnostic.
- Une exception ou erreur fatale non rattrapée = échec (code non nul) : rattrape-la (try/catch \\Throwable) si le bug
  EST l'exception, pour conclure proprement avec exit(1).
- Le test doit ÉCHOUER sur le code d'avant le correctif et PASSER après. Pas de sortie HTML, pas de navigateur."""

EXAMPLE_PHP = """--- EXEMPLE de forme (autre sujet) :
```php
<?php
require 'config/config.inc.php';
$p = new Product(1, false, 1);
$price = Product::getPriceStatic(1, false);
echo "prix HT produit 1 : $price\\n";
exit($price > 0 ? 0 : 1);
```"""

FORMAT_PHP = """RÉPONDS EXACTEMENT dans ce format (rien d'autre) :
KIND: php
```php
<?php
// contenu de l'oracle PHP
```"""


def catalog(pr):
    for f in ("catalog.jsonl", "catalog_legacy.jsonl"):
        p = B / f
        if p.exists():
            for l in open(p):
                c = json.loads(l)
                if c["pr"] == pr:
                    return c
    raise SystemExit(f"#{pr} absent du catalogue")


def example(kind):
    d = B / "replay" / str(EXAMPLES[kind])
    spec = next(d.glob("oracle*.spec.js")).read_text()
    setup = (d / "setup.sql").read_text() if (d / "setup.sql").exists() else ""
    return f"--- EXEMPLE de test validé ({kind}, autre bug)\n```sql\n{setup[:1500]}\n```\n```js\n{spec[:3500]}\n```"


def context(bug):
    diff = (B / "diffs" / f"{bug['pr']}.diff").read_text()[:6000]
    kws = [fn.split("::")[-1] for fns in bug["functions"].values() for fn in fns if not fn.startswith("(")][:6]
    code = "\n\n".join(f"===== {f} =====\n{flow.windows(flow.show(bug['base_commit'], f), kws)[:3000]}"
                       for f in bug["files"][:2] if flow.show(bug["base_commit"], f))
    return (f"TICKET\n{flow.ticket_text(bug)[:3000]}\n\nCORRECTIF OFFICIEL (diff)\n```diff\n{diff}\n```\n\n"
            f"CODE AVANT CORRECTIF (extraits)\n{code[:6000]}\n\n{schema(bug, diff + code)}")


def schema(bug, text):
    """Tables RÉELLES de la base (db_structure.sql au commit de base) : liste complète + schéma des tables citées.
    Évite les tables inventées dans setup.sql (ex. ps_tab_access, inexistante)."""
    sql = flow.show(bug["base_commit"], "install-dev/data/db_structure.sql")
    tables = dict(re.findall(r"CREATE TABLE `PREFIX_(\w+)` \((.*?)\n\)", sql, re.S))
    if not tables:
        return ""
    low = text.lower()
    cited = [t for t in sorted(tables, key=len, reverse=True) if re.search(rf"\b(ps_|_db_prefix_\s*\.\s*')?{t}\b", low)][:6]
    detail = "\n\n".join(f"CREATE TABLE ps_{t} ({tables[t].strip()[:1500]}\n)" for t in cited)
    return ("TABLES DE LA BASE (préfixe ps_, n'utilise AUCUNE autre table dans setup.sql)\n"
            + ", ".join(sorted(tables)) + (f"\n\nSCHÉMA DES TABLES CITÉES\n{detail}" if detail else ""))


def parse(reply):
    kind = "bo" if re.search(r"KIND:\s*bo", reply, re.I) else "fo"
    sql = re.search(r"```sql\n(.*?)```", reply, re.S)
    if re.search(r"KIND:\s*php", reply, re.I) or re.search(r"```php\n", reply):
        php = re.search(r"```php\n(.*?)```", reply, re.S)
        return "php", (sql.group(1).strip() if sql else ""), (php.group(1).strip() if php else "")
    js = re.search(r"```(?:js|javascript)\n(.*?)```", reply, re.S)
    return kind, (sql.group(1).strip() if sql else ""), (js.group(1).strip() if js else "")


def sh(cmd, timeout=1800):
    r = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=timeout)
    return r.returncode, r.stdout + r.stderr


def validate(pr, gdir):
    """(ok, message) : ok si échec en pre ET succès en post."""
    sh(f"PSB={PSB} {B}/checkout.sh {pr} pre")
    c_pre, o_pre = sh(f"PSB={PSB} {B}/replay/run.sh {gdir.name}", 600)
    sh(f"PSB={PSB} {B}/checkout.sh {pr} post")
    c_post, o_post = sh(f"PSB={PSB} {B}/replay/run.sh {gdir.name}", 600)
    php = (gdir / "oracle_gemma.php").exists()
    tail = lambda o: "\n".join(o.strip().splitlines()[-30:])[:2000]

    def err(o):
        if php:  # sortie complète de l'oracle PHP (valeurs affichées, erreur fatale, trace)
            return tail(o) or "aucune sortie"
        e = "\n".join(l for l in o.splitlines() if re.search(r"Error|Expected|Received|Timeout|✘|SyntaxError|at .*spec", l))[:1500]
        # aucune ligne reconnue (ex. setup.sql refusé par MySQL : run.sh s'arrête sans sortie Playwright) → fin brute
        return e or ("\n".join(o.strip().splitlines()[-25:])[:1500] or "aucune sortie : le setup.sql a probablement échoué (vérifie la syntaxe SQL et les tables ps_*)")
    if c_pre != 0 and c_post == 0:
        return True, "OK"
    if c_pre == 0:
        seen = f"\nSortie sur le code AVANT correctif :\n{tail(o_pre)}" if php else ""
        return False, "Le test PASSE déjà sur le code AVANT correctif : il ne détecte pas le bug. Rends l'assertion plus précise." + seen
    return False, f"Le test ÉCHOUE sur le code APRÈS correctif (il devrait passer) :\n{err(o_post)}{'' if php else snapshot(gdir)}"


def snapshot(gdir):
    """Arbre d'accessibilité de la page au moment de l'échec (error-context.md de Playwright) : les vrais libellés
    et rôles de la page, pour que Gemma corrige ses sélecteurs au lieu de deviner (cf. bench/reprotest.py)."""
    port = 8080 + int(PSB)
    ctx = sorted((B / "replay" / f"test-results-{port}").glob(f"{gdir.name}-*/error-context.md"), key=lambda p: p.stat().st_mtime)
    if not ctx:
        return ""
    snap = ctx[-1].read_text(errors="ignore")
    snap = snap[snap.find("# Page snapshot"):] if "# Page snapshot" in snap else snap
    return f"\n\nÉTAT DE LA PAGE AU MOMENT DE L'ÉCHEC (arbre d'accessibilité, tronqué) :\n{snap[:5000]}"


EXPLORE_ASK = """AVANT d'écrire le test, tu peux OBSERVER 1 ou 2 pages de la boutique (code AVANT correctif) :
on t'en donnera l'arbre d'accessibilité réel (titres, boutons, champs, colonnes).
Réponds UNIQUEMENT en JSON : {"pages": [{"kind": "bo", "cible": "Catalogue > Produits"}, {"kind": "fo", "cible": "/index.php?id_product=1&controller=product"}]}
- bo : chemin du menu en libellés français (ex. "Commandes > Commandes", "Paramètres avancés > Performances") ou URL /admin-dev/index.php?controller=AdminXxx
- fo : URL relative."""


def explore(pr, reply):
    """Ouvre les pages demandées par le modèle sur la boutique AVANT correctif → texte des arbres d'accessibilité."""
    pages = []
    try:
        pages = json.loads(re.search(r"\{.*\}", reply, re.S).group(0)).get("pages", [])[:2]
    except Exception:
        pass
    if not pages:
        return "PAGES OBSERVÉES : aucune (réponse JSON illisible)."
    sh(f"PSB={PSB} {B}/checkout.sh {pr} pre")
    port = 8080 + int(PSB)
    out = []
    for p in pages:
        kind, cible = ("bo" if p.get("kind") == "bo" else "fo"), str(p.get("cible", ""))[:200]
        _, o = sh(f"cd {B / 'replay'} && PS_PORT={port} timeout 150 node explore.js {kind} {json.dumps(cible)}", 200)
        out.append(f"===== {kind} : {cible} =====\n{o[:6000]}")
    return "PAGES OBSERVÉES (code AVANT correctif)\n" + "\n\n".join(out)


def signatures(bug):
    """Signatures RÉELLES (commit de base) des classes des fichiers touchés : namespace, classe, méthodes.
    Évite les appels inventés (lot 4 : 8 échecs sur 16 = erreurs PHP dans le test, API 8.0/8.1 méconnue)."""
    out = []
    for f in bug["files"]:
        if not f.endswith(".php"):
            continue
        src = flow.show(bug["base_commit"], f)
        if not src:
            continue
        ns = re.search(r"^namespace\s+([\w\\]+);", src, re.M)
        cls = re.findall(r"^\s*(?:abstract\s+|final\s+)*(?:class|interface|trait)\s+(\w+)[^\n{]*", src, re.M)
        meths = re.findall(r"^\s*((?:public|protected|private)?\s*(?:static\s+)?function\s+\w+\s*\([^)]*\)(?:\s*:\s*[\w\\?|]+)?)", src, re.M)
        head = f"// {f}" + (f"\nnamespace {ns.group(1)};" if ns else "") + "".join(f"\nclass {c}" for c in cls[:1])
        out.append(head + "\n" + "\n".join("  " + " ".join(m.split()) for m in meths[:40]))
    return "SIGNATURES RÉELLES (code avant correctif) — n'appelle que ces méthodes, avec ces paramètres :\n" + "\n\n".join(out) if out else ""


def required_fields(bug):
    """Champs requis ('required' => true) des classes ObjectModel legacy touchées ou citées dans le correctif."""
    diff = (B / "diffs" / f"{bug['pr']}.diff").read_text()
    names = set(re.findall(r"\bnew\s+(\w+)\s*\(", diff)) | {Path(f).stem for f in bug["files"] if f.startswith("classes/")}
    names |= {"Order", "Cart", "Customer", "Product"}
    out = []
    for n in sorted(names):
        path = next((f for f in (f"classes/{n}.php", f"classes/order/{n}.php", f"classes/checkout/{n}.php") if flow.show(bug["base_commit"], f)), None)
        if not path:
            continue
        src = flow.show(bug["base_commit"], path)
        i = src.find("$definition")
        src = src[i:src.find("\n    ];", i)] if i >= 0 else ""  # bloc $definition seul (pas $webserviceParameters)
        req = re.findall(r"'(\w+)'\s*=>\s*\[[^\]]*'required'\s*=>\s*true", src)
        if req:
            out.append(f"- {n} : {', '.join(r for r in dict.fromkeys(req) if r != 'fields')}")
    return "CHAMPS REQUIS pour créer ces objets (sinon exception « La propriété X->y est vide ») :\n" + "\n".join(out) if out else ""


def process(pr, model, tries, explore_first=False, mode="ui"):
    bug = catalog(pr)
    gdir = B / "replay" / f"g{pr}"
    gdir.mkdir(exist_ok=True)
    kind0 = "bo" if bug.get("area") == "BO" or any("admin" in f.lower() or "/Admin/" in f for f in bug["files"]) else "fo"
    fmt = FORMAT_PHP if mode == "php" else FORMAT
    if mode == "php":  # oracle PHP en ligne de commande : pas de navigateur, pas de sélecteurs
        explore_first = False
        intro = f"{ENV_PHP}\n\n{EXAMPLE_PHP}\n\n{context(bug)}\n\n{signatures(bug)}\n\n{required_fields(bug)}"
        role = "Tu écris des tests PHP de non-régression pour PrestaShop (exécutés en ligne de commande)."
    else:
        intro = f"{ENV_NOTES}\n\n{example(kind0)}\n\n{context(bug)}"
        role = "Tu écris des tests Playwright de non-régression pour PrestaShop."
    msgs = [{"role": "system", "content": role + " Réponds uniquement dans le format demandé."}]
    if explore_first:  # étape d'observation : le modèle choisit les pages, on lui montre leur contenu réel
        msgs.append({"role": "user", "content": f"{intro}\n\n{EXPLORE_ASK}"})
        reply, usage = agentrun.chat(msgs, model)
        agentrun.spend(usage)
        msgs += [{"role": "assistant", "content": reply}, {"role": "user", "content": f"{explore(pr, reply)}\n\nÉcris maintenant le test.\n{FORMAT}"}]
    else:
        msgs.append({"role": "user", "content": f"{intro}\n\n{fmt}"})
    t0, log = time.time(), []
    for attempt in range(tries):
        reply, usage = agentrun.chat(msgs, model)
        agentrun.spend(usage)
        msgs.append({"role": "assistant", "content": reply})
        kind, sql, js = parse(reply)
        for f in list(gdir.glob("oracle_gemma*.spec.js")) + list(gdir.glob("oracle_gemma*.php")):
            f.unlink()
        if not js:
            msg = f"Réponse sans bloc ```{'php' if mode == 'php' else 'js'}```. Respecte le format."
        else:
            if kind == "php":
                code = js if js.lstrip().startswith("<?php") else "<?php\n" + js
                (gdir / "oracle_gemma.php").write_text(code.replace("<?php", f"<?php\n// Oracle écrit par Gemma ({model}) pour PR #{pr}, validé pre/post automatiquement", 1) + "\n")
            else:
                (gdir / f"oracle_gemma{'.bo' if kind == 'bo' else ''}.spec.js").write_text(f"// Oracle écrit par Gemma ({model}) pour PR #{pr}, validé pre/post automatiquement\n{js}\n")
            (gdir / "setup.sql").write_text(sql + "\n") if sql and kind != "php" else (gdir / "setup.sql").unlink(missing_ok=True)
            ok, msg = validate(pr, gdir)
            log.append({"attempt": attempt + 1, "ok": ok, "msg": msg[:300]})
            if ok:
                (gdir / "STATUS").write_text(f"valide\ngemma essai {attempt + 1}\n")
                return {"pr": pr, "statut": "valide", "essais": attempt + 1, "s": round(time.time() - t0), "log": log}
        msgs.append({"role": "user", "content": f"VALIDATION : {msg}\nCorrige le test. {fmt}"})
    (gdir / "STATUS").write_text(f"exclu:gemma_{tries}_essais\n{log[-1]['msg'] if log else ''}\n")
    return {"pr": pr, "statut": "echec", "essais": tries, "s": round(time.time() - t0), "log": log}


def main():
    agentrun.load_env()
    ap = argparse.ArgumentParser()
    ap.add_argument("prs", nargs="+", type=int)
    ap.add_argument("--model", default=os.environ.get("LLM_MODEL", "gemma-4-31b-it"))
    ap.add_argument("--tries", type=int, default=3)
    ap.add_argument("--mode", choices=["ui", "php"], default="ui", help="ui = Playwright (FO/BO) ; php = oracle PHP en ligne de commande dans le conteneur")
    ap.add_argument("--explore", action="store_true", help="le modèle observe 1-2 pages réelles (code avant correctif) avant d'écrire")
    a = ap.parse_args()
    with open(B / "gentest.jsonl", "a") as out:
        for pr in a.prs:
            if (B / "replay" / f"g{pr}" / "STATUS").exists():  # déjà traité (valide ou exclu) : on ne refait pas
                continue
            try:
                r = process(pr, a.model, a.tries, a.explore, a.mode)
            except Exception as e:
                r = {"pr": pr, "statut": "erreur", "err": str(e)[:300]}
            print(json.dumps({k: v for k, v in r.items() if k != "log"}, ensure_ascii=False), flush=True)
            out.write(json.dumps(r, ensure_ascii=False) + "\n")
            out.flush()


if __name__ == "__main__":
    main()
