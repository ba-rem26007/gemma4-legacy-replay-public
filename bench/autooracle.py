#!/usr/bin/env python3
"""Génération AUTOMATIQUE d'oracles par différentiel pre/post (golden master), sans modèle.

Pour un bug :
  1. pages cibles déduites des fichiers touchés (contrôleurs FO/BO, classes, templates)
  2. capture (texte visible normalisé + statut HTTP + erreurs PHP) en pre, deux fois (→ lignes instables écartées)
  3. capture en post ; lignes stables qui apparaissent / disparaissent = signature du correctif
  4. écrit bench/replay/<pr>/oracle_auto(.bo).spec.js puis le VALIDE (échoue en pre, passe en post)
La boutique tourne en mode debug pendant les captures (notices/warnings PHP visibles).

Usage : [PSB=n] python3 bench/autooracle.py <pr> [<pr>…] [--checkout bench/checkout.sh]
Sortie : bench/replay/<pr>/oracle_auto*.spec.js + STATUS_AUTO ; résumé bench/autooracle.jsonl
"""
import argparse, json, os, re, subprocess, sys, time
from pathlib import Path

B = Path(__file__).resolve().parent
PSB = os.environ.get("PSB", "1")
PORT = int(os.environ.get("PS_PORT", 8080 + int(PSB)))
PROJ = "psbench" + ("" if PSB == "1" else PSB)
BASE_URL = f"http://localhost:{PORT}"

FO_CORE = ["/index.php", "/index.php?controller=category&id_category=3", "/index.php?controller=category&id_category=4",
           "/index.php?id_product=1&controller=product", "/index.php?id_product=2&controller=product",
           "/index.php?id_product=5&controller=product", "/index.php?id_product=7&controller=product",
           "/index.php?controller=search&s=shirt", "/index.php?controller=prices-drop", "/index.php?controller=new-products",
           "/index.php?controller=best-sales", "/index.php?controller=cms&id_cms=1", "/index.php?controller=contact",
           "/index.php?controller=manufacturer&id_manufacturer=1", "/index.php?controller=supplier&id_supplier=1",
           "/index.php?controller=sitemap", "/index.php?controller=cart&action=show", "/index.php?controller=stores"]
FO_BY_CTRL = {"product": FO_CORE[3:7], "category": FO_CORE[1:3], "search": [FO_CORE[7]], "cms": [FO_CORE[11]],
              "manufacturer": [FO_CORE[13]], "supplier": [FO_CORE[14]], "cart": [FO_CORE[16]], "index": [FO_CORE[0]]}
VOLATILE = re.compile(r"\d{1,2}[/:-]\d{1,2}[/:-]\d{2,4}|\d{2}:\d{2}(:\d{2})?|token=\w+|_token=[\w-]+|[a-f0-9]{24,}|"
                      r"\bid_cart\b.*|\b\d+(\.\d+)?\s?(ms|s|Mo|MB|Ko|KB)\b")
PHP_ERR = re.compile(r"(Fatal error|Warning|Notice|Deprecated|Uncaught|Exception|Stack trace|Whoops)[^\n]{0,160}", re.I)


def sh(cmd, timeout=1800):
    r = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=timeout)
    return r.returncode, r.stdout + r.stderr


def catalog(pr):
    return next(json.loads(l) for f in ("catalog.jsonl", "catalog_legacy.jsonl") if (B / f).exists()
                for l in open(B / f) if json.loads(l)["pr"] == pr)


GENERIC_WORDS = {"admin", "controller", "core", "handler", "query", "command", "builder", "filters", "filter", "grid",
                 "definition", "factory", "type", "form", "data", "provider", "repository", "presenter", "lazy", "array",
                 "abstract", "interface", "get", "for", "viewing", "update", "updater", "adapter", "search", "util", "string"}


def words(path):
    """Mots métier d'un nom de fichier CamelCase (FeatureFilters → feature)."""
    return [w.lower() for w in re.findall(r"[A-Z][a-z]+", Path(path).stem) if w.lower() not in GENERIC_WORDS and len(w) > 3]


def targets(bug):
    """(fo_urls, bo_mots) déduits des fichiers touchés. bo_mots = ['*'] → tout le menu BO."""
    fo, bo = [], set()
    for f in bug["files"]:
        name = Path(f).stem
        m = re.match(r"controllers/front/(?:listing/)?(\w+)Controller", f)
        if m:
            fo += FO_BY_CTRL.get(m.group(1).lower(), [f"/index.php?controller={m.group(1).lower()}"])
        if f.startswith(("controllers/admin", "admin", "src/", "classes/")) and not f.startswith("src/Adapter/Presenter"):
            bo.update(words(f) or ["*"])
        if f.startswith(("themes/", "classes/", "src/Adapter/Presenter", "src/Core/Product", "src/Adapter/Product",
                         "src/Adapter/Cart", "controllers/front")) or f.endswith(".tpl"):
            fo += FO_CORE
    fo = list(dict.fromkeys(fo))
    return (fo if fo or not bo else []), sorted(bo)


CAPTURE_JS = r"""
const { chromium } = require('@playwright/test');
(async () => {
  const [base, out, foJson, boJson] = process.argv.slice(2);
  const fo = JSON.parse(foJson), bo = JSON.parse(boJson);
  const browser = await chromium.launch(); const page = await browser.newPage({ locale: 'fr-FR' });
  const res = {};
  const grab = async (key, url) => {
    try {
      const r = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
      const txt = await page.evaluate(() => (document.querySelector('#main, #content, main, #content-wrapper') || document.body).innerText);
      res[key] = { status: r ? r.status() : 0, text: txt.slice(0, 40000), url: page.url() };
    } catch (e) { res[key] = { status: -1, text: String(e).slice(0, 200) }; }
  };
  for (const u of fo) await grab('FO ' + u, base + u);
  if (bo.length) {
    await page.goto(base + '/admin-dev/index.php?controller=AdminLogin');
    await page.fill('#email', 'demo@prestashop.com'); await page.fill('#passwd', 'prestashop_demo');
    await page.click('#submit_login'); await page.waitForLoadState('networkidle').catch(() => {});
    const links = [...new Set(await page.$$eval('#nav-sidebar a[href], .main-menu a[href], #main-menu a[href], nav a[href]',
      as => as.map(a => a.href).filter(h => /controller=|\/admin-dev\/index\.php\//.test(h))))];
    const keyOf = h => { const m = h.match(/controller=(\w+)/) || h.match(/index\.php\/([\w\/-]+)/); return m ? m[1].replace(/\//g, '_') : h; };
    let chosen = bo.includes('*') ? links : links.filter(h => bo.some(w => h.toLowerCase().includes(w)));
    if (!chosen.length) chosen = links;
    for (const href of chosen.slice(0, 40)) {
      const c = keyOf(href);
      await grab('BO ' + c, href);
      const risk = page.locator('text=/comprends les risques|understand the risks|Afficher la page/i').first();
      if (await risk.count()) { await risk.click().catch(() => {}); await page.waitForLoadState('domcontentloaded').catch(() => {});
        const txt = await page.evaluate(() => document.body.innerText); res['BO ' + c].text = txt.slice(0, 40000); }
    }
  }
  require('fs').writeFileSync(out, JSON.stringify(res));
  await browser.close();
})();
"""


def capture(fo, bo, tag, work):
    js = work / "capture.js"
    js.write_text(CAPTURE_JS)
    out = work / f"cap_{tag}.json"
    code, log = sh(f"cd {B / 'replay'} && node {js} {BASE_URL} {out} '{json.dumps(fo)}' '{json.dumps(bo)}'", timeout=900)
    return json.loads(out.read_text()) if out.exists() else {}


def lines(entry):
    t = entry.get("text", "")
    out = set()
    for l in t.splitlines():
        l = VOLATILE.sub("#", l.strip())
        if 3 <= len(l) <= 200:
            out.add(l)
    out.add(f"__status__{entry.get('status')}")
    for m in PHP_ERR.finditer(t):
        out.add("__php__" + VOLATILE.sub("#", m.group(0)[:120]))
    return out


def dev_mode(on):
    """Bascule _PS_MODE_DEV_ (1.6/1.7/8/9 : config/defines.inc.php)."""
    v = "true" if on else "false"
    sh(f"docker exec {PROJ}-ps-1 sed -i \"s/define('_PS_MODE_DEV_', \\(true\\|false\\))/define('_PS_MODE_DEV_', {v})/\" /var/www/html/config/defines.inc.php")


def js_str(s):
    return json.dumps(s, ensure_ascii=False)


def write_spec(pr, key, entry_post, added, removed, work):
    is_bo = key.startswith("BO ")
    target = key[3:]
    d = B / "replay" / str(pr)
    d.mkdir(exist_ok=True)
    checks = []
    for l in added[:3]:
        if l.startswith("__status__"):
            checks.append(f"  expect(status).toBe({int(l[10:])});")
        elif not l.startswith("__php__"):
            checks.append(f"  await expect(zone).toContainText({js_str(l)});")
    for l in removed[:3]:
        if l.startswith("__php__"):
            checks.append(f"  await expect(page.locator('body')).not.toContainText({js_str(l[7:60])});")
        elif not l.startswith("__status__"):
            checks.append(f"  await expect(zone).not.toContainText({js_str(l)});")
    if not checks:
        return None
    nav = (f"  const r = await page.goto({js_str(target)});\n  const status = r ? r.status() : 0;" if not is_bo else
           f"""  await page.goto('/admin-dev/');
  const href = await page.locator('a[href*="{target.replace('Admin', '').lower()}" i]').first().getAttribute('href').catch(() => null);
  const r = await page.goto(href || '/admin-dev/index.php?controller={target}');
  const risk = page.locator('text=/comprends les risques|understand the risks/i').first();
  if (await risk.count()) await risk.click();
  const status = r ? r.status() : 0;""")
    spec = f"""// Oracle GÉNÉRÉ AUTOMATIQUEMENT (bench/autooracle.py, différentiel pre/post) — PR #{pr} — page : {key}
// Ne pas éditer à la main ; régénérer. Mode debug activé pendant le test (erreurs PHP visibles).
const {{ test, expect }} = require('@playwright/test');
const {{ execSync }} = require('child_process');
const PROJ = 'psbench' + ((process.env.PS_PORT || '8081') === '8081' ? '' : String(Number(process.env.PS_PORT) - 8080));
const dev = v => execSync(`docker exec ${{PROJ}}-ps-1 sed -i "s/define('_PS_MODE_DEV_', \\\\(true\\\\|false\\\\))/define('_PS_MODE_DEV_', ${{v}})/" /var/www/html/config/defines.inc.php`);
test.beforeAll(() => dev('true'));
test.afterAll(() => dev('false'));
test('autooracle #{pr}', async ({{ page }}) => {{
{nav}
  const zone = page.locator('#main, #content, main, #content-wrapper, body').first();
{chr(10).join(checks)}
}});
"""
    p = d / ("oracle_auto.bo.spec.js" if is_bo else "oracle_auto.spec.js")
    p.write_text(spec)
    return p


def run_oracle(pr, checkout, mode):
    sh(f"PSB={PSB} {checkout} {pr} {mode}")
    code, out = sh(f"cd {B / 'replay'} && PSB={PSB} PS_PORT={PORT} npx playwright test {pr}/oracle_auto", timeout=600)
    return code == 0, out


def process(pr, checkout):
    bug = catalog(pr)
    fo, bo = targets(bug)
    work = B / "replay" / str(pr) / ".auto"
    work.mkdir(parents=True, exist_ok=True)
    t = time.time()
    caps = {}
    for tag, mode in (("pre1", "pre"), ("pre2", "pre"), ("post", "post")):
        code, log = sh(f"PSB={PSB} {checkout} {pr} {mode}")
        if code:
            return {"pr": pr, "statut": "exclu:checkout", "log": log[-300:]}
        dev_mode(True)
        caps[tag] = capture(fo, bo, tag, work)
        dev_mode(False)
    best = None
    for key in caps["post"]:
        if key not in caps["pre1"] or key not in caps["pre2"]:
            continue
        a, b, c = lines(caps["pre1"][key]), lines(caps["pre2"][key]), lines(caps["post"][key])
        stable_pre = a & b
        unstable = a ^ b
        added = sorted(c - a - b - unstable, key=len, reverse=True)
        removed = sorted(stable_pre - c, key=len, reverse=True)
        # préférence : erreur PHP disparue > statut HTTP changé > texte
        score = 3 * any(l.startswith("__php__") for l in removed) + 2 * any(l.startswith("__status__") for l in added) + bool(added or removed)
        if score and (best is None or score > best[0]):
            best = (score, key, added, removed)
    if not best:
        return {"pr": pr, "statut": "exclu:aucun_differentiel", "pages": len(caps["post"]), "s": round(time.time() - t)}
    spec = write_spec(pr, best[1], caps["post"][best[1]], best[2], best[3], work)
    if not spec:
        return {"pr": pr, "statut": "exclu:differentiel_inexploitable", "s": round(time.time() - t)}
    ok_pre, _ = run_oracle(pr, checkout, "pre")
    ok_post, out_post = run_oracle(pr, checkout, "post")
    statut = "valide" if (not ok_pre and ok_post) else f"exclu:non_discriminant(pre={'ok' if ok_pre else 'ko'},post={'ok' if ok_post else 'ko'})"
    if statut != "valide":
        spec.unlink()
    (B / "replay" / str(pr) / "STATUS_AUTO").write_text(f"{statut}\n{best[1]} | +{best[2][:2]} -{best[3][:2]}\n")
    return {"pr": pr, "statut": statut, "page": best[1], "s": round(time.time() - t)}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("prs", nargs="+", type=int)
    ap.add_argument("--checkout", default=str(B / "checkout.sh"))
    a = ap.parse_args()
    with open(B / "autooracle.jsonl", "a") as log:
        for pr in a.prs:
            r = process(pr, a.checkout)
            print(json.dumps(r, ensure_ascii=False), flush=True)
            log.write(json.dumps(r, ensure_ascii=False) + "\n")


if __name__ == "__main__":
    main()
