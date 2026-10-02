// Issue #41193 : les traductions d'un thème ENFANT doivent être chargées via ThemeProviderDefinition
// dans l'éditeur de traductions BO (API listDomainTranslation) ; avant le correctif les thèmes enfants
// passaient par le fournisseur du cœur et leurs chaînes n'apparaissaient pas.
// Le test importe (si besoin) un thème enfant minimal de classic (benchchild.zip, fourni à côté)
// dont index.tpl contient {l s='Bench child theme string ENG' d='Shop.Theme.Global'}, puis ouvre
// International > Traductions > Front-office > benchchild > fr et vérifie la présence de la chaîne
// dans le domaine Shop.Theme.Global.
const path = require('path');
const { test, expect } = require('@playwright/test');

const STRING = 'Bench child theme string ENG';

test('les chaînes du thème enfant apparaissent dans l’éditeur de traductions', async ({ page }) => {
  await page.goto('/admin-dev/');
  await page.goto(await page.locator('a[href*="improve/design/themes/"]').first().getAttribute('href'));

  // Import du thème enfant s'il n'est pas déjà présent
  if (await page.getByText('Bench child', { exact: false }).count() === 0) {
    await page.goto(await page.locator('a[href*="improve/design/themes/import"]').first().getAttribute('href'));
    await page.locator('input[type="file"]').first().setInputFiles(path.join(__dirname, 'benchchild.zip'));
    await Promise.all([page.waitForNavigation(), page.locator('form button.btn-primary, form button[type="submit"]').last().click()]);
    await expect(page.getByText('Bench child', { exact: false }).first()).toBeAttached();
  }

  // Ouverture de l'éditeur de traductions pour le thème enfant
  await page.goto('/admin-dev/');
  await page.goto(await page.locator('a[href*="international/translations"]').first().getAttribute('href'));
  await page.selectOption('#form_translation_type', 'themes');
  await page.selectOption('#form_theme', 'benchchild');
  await page.selectOption('#form_language', 'fr');
  const first = page.waitForResponse(r => /\/api\/translations\/fr-FR\/[^/]+\/benchchild/.test(r.url()));
  await Promise.all([page.waitForNavigation(), page.locator('.card', { has: page.locator('#form_translation_type') }).locator('.card-footer button.btn-primary').click()]);
  const url = (await first).url().replace(/fr-FR\/[^/]+\/benchchild/, 'fr-FR/ShopThemeGlobal/benchchild');

  // Parcours des pages du domaine Shop.Theme.Global (même API que l'éditeur)
  const keys = [];
  for (let p = 1; p <= 20; p++) {
    const r = await page.request.get(url.replace(/([?&])page_index=\d+/, '$1').replace('?', `?page_index=${p}&page_size=19&`));
    expect(r.status()).toBe(200);
    const j = await r.json();
    keys.push(...Object.keys(j.data.data || {}));
    if (p >= (j.info.total_page || 1)) break;
  }
  expect(keys).toContain(STRING);
});
