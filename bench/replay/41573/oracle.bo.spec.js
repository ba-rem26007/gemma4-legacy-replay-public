// Issue #41573 : Réductions V2 (feature flag « discount ») — l'option « Mettre en avant »
// (highlight_in_cart) doit exister dans le formulaire, refléter la valeur enregistrée
// (réduction 941573 avec highlight = 1) et être sauvegardée à l'enregistrement.
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');

const PORT = process.env.PS_PORT || '8081';
const N = Number(PORT) - 8080;
const PROJ = `psbench${N === 1 ? '' : N}`;
const sql = q => execSync(`docker exec -i ${PROJ}-db-1 mysql -padmin -N prestashop`, { input: q, stdio: ['pipe', 'pipe', 'ignore'] }).toString().trim();

test.afterAll(() => sql("UPDATE ps_feature_flag SET state=0 WHERE name='discount';"));

test('réduction V2 : option de mise en avant', async ({ page }) => {
  await page.goto('/admin-dev/');
  const href = await page.locator('a[href*="sell/catalog/discounts"]').first().getAttribute('href');
  const tok = href.match(/_token=([^&#]+)/)[1];

  // Formulaire de création : l'option existe
  await page.goto(`/admin-dev/sell/catalog/discounts/new/cart_level?_token=${tok}`);
  await expect(page.locator('input[name="discount[information][highlight_in_cart]"]').first()).toBeAttached();

  // Édition : valeur enregistrée (1) reflétée, puis passage à « Non » et enregistrement
  await page.goto(`/admin-dev/sell/catalog/discounts/941573/edit?_token=${tok}`);
  const yes = page.locator('#discount_information_highlight_in_cart_1');
  await expect(yes, 'option highlight absente du formulaire').toBeAttached({ timeout: 5000 });
  await expect(yes).toBeChecked();
  await page.locator('#discount_information_highlight_in_cart_0').check({ force: true });
  await page.locator('form[name="discount"] button[type="submit"].btn-primary, button[type="submit"].btn-primary').first().click();
  await page.waitForLoadState('load');
  await expect.poll(() => sql('SELECT highlight FROM ps_cart_rule WHERE id_cart_rule=941573'), { timeout: 15000 }).toBe('0');
});
