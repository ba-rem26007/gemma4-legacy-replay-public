
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
