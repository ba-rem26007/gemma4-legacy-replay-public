// Issue #40999 : multiboutique — créer une boutique SANS importer de données puis afficher son front.
// Sans import, copyShopData() ne copiait pas les devises (ps_currency_shop) : le front de la nouvelle
// boutique était inutilisable. Le test : active le multiboutique (Paramètres > Général), crée la
// boutique « Bench 40999 » en décochant l'import de données, lui ajoute l'URL virtuelle /bench40999/,
// puis vérifie que le front de cette boutique s'affiche correctement (HTTP 200, page d'accueil rendue).
const { test, expect } = require('@playwright/test');

test('front d’une boutique créée sans import de données', async ({ page }) => {
  test.setTimeout(90_000);
  await page.goto('/admin-dev/');
  const prefs = await page.locator('a[href*="configure/shop/preferences/preferences"]').first().getAttribute('href');

  // 1. Activation du multiboutique
  await page.goto(prefs);
  await page.locator('#form_multishop_feature_active_1').check({ force: true });
  await Promise.all([page.waitForNavigation(), page.locator('#configuration_form button.btn-primary').first().click()]);

  // 2. Nouvelle boutique sans import de données
  await page.goto('/admin-dev/');
  const shopGroupUrl = await page.locator('a[href*="controller=AdminShopGroup"]').first().getAttribute('href');
  const token = shopGroupUrl.match(/token=[^&]+/)[0];
  await page.goto(`/admin-dev/index.php?controller=AdminShop&addshop=1&${token}`);
  await page.fill('input[name="name"]', 'Bench 40999');
  await page.locator('#useImportData_off').check({ force: true });
  await Promise.all([page.waitForNavigation(), page.locator('#shop_form_submit_btn').first().click()]);

  // 3. URL de la boutique : lien « Cliquez ici pour définir une URL pour cette boutique »
  await page.goto(await page.locator('a[href*="controller=AdminShopUrl"][href*="addshop_url"]').first().getAttribute('href'));
  await page.fill('#virtual_uri', 'bench40999');
  await Promise.all([page.waitForNavigation(), page.locator('#shop_url_form_submit_btn').first().click()]);
  await expect(page.locator('.alert-success').first()).toBeVisible();

  // 4. Front de la nouvelle boutique : doit s'afficher (avant : 500, TypeError de précision faute de devise)
  const fo = await page.request.get('/bench40999/');
  expect(fo.status()).toBe(200);
  const html = await fo.text();
  expect(html).toContain('/bench40999/');
  expect(html).toMatch(/<body[^>]*id="index"/);
});
