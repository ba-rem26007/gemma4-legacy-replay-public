// Issue #40795 : quand un module accroché à actionCartSave appelle Cart::getProducts() /
// Product::getPriceStatic(), changer de langue en front avec un panier non vide ne doit pas lever
// « If no employee is assigned in the context, cart ID must be provided to this method. »
// (le panier doit être dans le contexte avant Cart::update() dans FrontController::init()).
// Le test installe (si besoin) un module de test minimal (benchcartsave.zip, fourni à côté) inspiré du
// module du ticket ; en mode prod les exceptions de hook sont avalées, le module mémorise donc la
// dernière exception (BENCHCARTSAVE_ERROR, remise à zéro par setup.sql) et l'affiche sur sa page de
// configuration. Front : ajout d'un produit au panier puis bascule fr -> en -> fr -> en.
// (Fichier .bo pour la session BO ; la partie front utilise un contexte navigateur séparé.)
const path = require('path');
const { test, expect } = require('@playwright/test');

test('changement de langue avec panier non vide et module actionCartSave', async ({ page, browser, baseURL }) => {
  await page.goto('/admin-dev/');
  const modulesUrl = await page.locator('a[href*="improve/modules/manage"]').first().getAttribute('href');
  await page.goto(modulesUrl);
  if (await page.locator('[data-tech-name="benchcartsave"]').count() === 0) {
    await page.locator('#page-header-desc-configuration-add_module').click();
    const upload = page.waitForResponse(r => r.url().includes('/improve/modules/import') && r.request().method() === 'POST');
    await page.locator('.dz-hidden-input').first().setInputFiles(path.join(__dirname, 'benchcartsave.zip'));
    expect(await (await upload).text()).toContain('"status":true');
  }

  // Front : client anonyme, un produit au panier, puis changements de langue
  const ctx = await browser.newContext({ baseURL, locale: 'fr-FR' });
  const fo = await ctx.newPage();
  await fo.goto('/fr/index.php?id_product=1&id_product_attribute=3&controller=product');
  const added = fo.waitForResponse(r => r.url().includes('cart') && r.request().method() === 'POST');
  await fo.locator('[data-button-action="add-to-cart"]').click();
  await added;
  for (const url of ['/en/', '/fr/', '/en/']) {
    const resp = await fo.goto(url);
    expect(resp.status(), `GET ${url}`).toBe(200);
  }
  await ctx.close();

  // Aucune exception ne doit avoir été levée dans le hook
  await page.goto(modulesUrl.replace(/manage\/?\?/, 'manage/action/configure/benchcartsave?'));
  await expect(page.locator('#benchcartsave-error')).toHaveText('[]');
});
