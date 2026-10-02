// Issue #40070 : le hook actionCarrierUpdate doit être déclenché à l'enregistrement d'un transporteur
// sur la page Symfony (migrée) des transporteurs. Le test installe (si besoin) un petit module de test
// (benchcarrierhook.zip, fourni à côté) accroché à actionCarrierUpdate qui mémorise l'id du transporteur
// dans BENCHCARRIERHOOK_LAST (remis à zéro par setup.sql) et l'affiche sur sa page de configuration.
const path = require('path');
const { test, expect } = require('@playwright/test');

test('actionCarrierUpdate déclenché à l’édition d’un transporteur (page migrée)', async ({ page }) => {
  await page.goto('/admin-dev/');
  const modulesUrl = await page.locator('a[href*="improve/modules/manage"]').first().getAttribute('href');
  const carriersUrl = await page.locator('a[href*="improve/shipping/carriers"]').first().getAttribute('href');

  // Installation du module de test s'il est absent
  await page.goto(modulesUrl);
  if (await page.locator('[data-tech-name="benchcarrierhook"]').count() === 0) {
    await page.locator('#page-header-desc-configuration-add_module').click();
    const upload = page.waitForResponse(r => r.url().includes('/improve/modules/import') && r.request().method() === 'POST');
    await page.locator('.dz-hidden-input').first().setInputFiles(path.join(__dirname, 'benchcarrierhook.zip'));
    const r = await upload;
    expect(await r.text()).toContain('"status":true');
  }

  // Édition du transporteur 3 (« My cheap carrier », sans commande donc sans nouvelle version) et enregistrement sans modification
  await page.goto(carriersUrl.replace(/carriers\/?\?/, 'carriers/3/edit?'));
  await Promise.all([
    page.waitForNavigation(),
    page.locator('form[name="carrier"] button[type="submit"], #save-button, button.btn-primary:has-text("Enregistrer")').first().click(),
  ]);
  await expect(page.locator('.alert-success:visible').first()).toBeVisible();

  // Le module doit avoir reçu le hook
  await page.goto(modulesUrl.replace(/manage\/?\?/, 'manage/action/configure/benchcarrierhook?'));
  await expect(page.locator('#benchcarrierhook-last')).toHaveText('[carrier-3]');
});
