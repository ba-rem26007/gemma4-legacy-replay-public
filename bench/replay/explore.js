// Exploration d'une page pour l'écriture d'un test (gentest --explore) : ouvre la page et affiche son arbre
// d'accessibilité (rôles, libellés, champs), pour que le modèle écrive ses sélecteurs d'après la VRAIE page.
// Usage : PS_PORT=8084 node explore.js fo "/index.php?id_product=1&controller=product"
//         PS_PORT=8084 node explore.js bo "Catalogue > Produits"      (chemin de menu, libellés FR, regex souple)
//         PS_PORT=8084 node explore.js bo "/admin-dev/index.php?controller=AdminOrders"
const { chromium } = require('@playwright/test');
const PORT = process.env.PS_PORT || 8081;
const BASE = `http://localhost:${PORT}`;
const [kind, target = ''] = process.argv.slice(2);

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ locale: 'fr-FR' });
  page.setDefaultTimeout(20_000);
  const risk = async () => {
    const r = page.getByText(/comprends les risques|understand the risks/i);
    if (await r.count()) await r.first().click().catch(() => {});
  };
  try {
    if (kind === 'bo') {
      await page.goto(`${BASE}/admin-dev/index.php?controller=AdminLogin`);
      await page.fill('#email', 'demo@prestashop.com');
      await page.fill('#passwd', 'prestashop_demo');
      await page.click('#submit_login');
      await page.waitForURL(/AdminDashboard|dashboard/i, { timeout: 60_000 });
      if (target.startsWith('/')) {
        await page.goto(BASE + target);
        await risk();
      } else if (target) {
        // chemin de menu : on clique chaque libellé dans la barre latérale (href récupéré → jeton inclus)
        for (const label of target.split('>').map(s => s.trim()).filter(Boolean)) {
          const link = page.locator('#nav-sidebar a, .main-menu a, nav a').filter({ hasText: new RegExp(label, 'i') }).first();
          const href = await link.getAttribute('href').catch(() => null);
          if (href) { await page.goto(new URL(href, page.url()).href); await risk(); }
          else console.log(`(libellé de menu introuvable : ${label})`);
        }
      }
    } else {
      await page.goto(BASE + (target || '/'));
    }
    await page.waitForLoadState('domcontentloaded');
    console.log(`URL : ${page.url().replace(BASE, '')}`);
    console.log(`TITRE : ${await page.title()}`);
    // contenu principal seulement (pas l'en-tête ni le menu), URL à jeton retirées
    let root = page.locator('body');
    for (const sel of ['#main-div', '#content-wrapper', '#content', 'main', '#wrapper']) {
      if (await page.locator(sel).count()) { root = page.locator(sel).first(); break; }
    }
    const snap = (await root.ariaSnapshot()).split('\n').filter(l => !/^\s*- \/url:/.test(l)).join('\n');
    console.log(snap.slice(0, 7000));
  } catch (e) {
    console.log(`ERREUR exploration : ${String(e).slice(0, 300)}`);
    console.log(`URL : ${page.url().replace(BASE, '')}`);
  }
  await browser.close();
})();
