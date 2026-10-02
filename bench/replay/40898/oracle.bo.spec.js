// Issue #40898 : [Multiboutique] reserved_quantity n'est pas mise à jour quand le groupe de
// boutiques partage les quantités disponibles (lignes stock_available avec id_shop = 0).
// setup.sql : 2 boutiques dans un groupe avec stock partagé, commande n° 2 (1 × « Mug Today is
// a good day », non expédiée) remise à l'état initial, quantité réservée du mug remise à 0.
// Le test passe la commande « En attente de paiement » en BO (ce qui resynchronise le stock), puis vérifie dans
// Catalogue > Stock que la quantité réservée du mug vaut 1 (et non 0).
const { test, expect } = require('@playwright/test');

async function bo(page, url) {
  await page.goto(url);
  if (page.url().includes('security/compromised')) await page.locator('a:has-text("Oui")').first().click();
}

test('quantité réservée mise à jour avec stock partagé', async ({ page }) => {
  await bo(page, '/admin-dev/index.php?controller=AdminOrders&id_order=2&vieworder&setShopContext=s-1');
  await expect(page).toHaveURL(/orders\/2\/view/);
  const form = page.locator('#update_order_status_action_form');
  // liste select2 : on positionne la valeur et on déclenche l'événement change (active le bouton)
  await page.evaluate(() => window.$('#update_order_status_action_input').val('14').trigger('change'));
  await expect(page.locator('#update_order_status_action_btn')).toBeEnabled();
  await Promise.all([
    page.waitForURL(/orders\/2\/view/),
    page.waitForResponse(r => r.url().includes('/orders/2/status') && r.request().method() === 'POST'),
    page.locator('#update_order_status_action_btn').click(),
  ]);
  await expect(page.locator('.alert-success', { hasText: /réussie/i }).first()).toBeVisible();

  await bo(page, '/admin-dev/index.php?controller=AdminStockManagement&setShopContext=s-1');
  await page.waitForResponse(r => r.url().includes('/api/stocks/'));
  const search = page.locator('.tags-input input, input.form-control.input[placeholder=""]').first();
  const res = page.waitForResponse(r => r.url().includes('/api/stocks/') && r.url().includes('good'));
  await search.fill('good');
  await search.press('Enter');
  const body = await (await res).json();
  const rows = (body.data?.data || body.data || []).filter(p => Number(p.product_id) === 8 && Number(p.combination_id || 0) === 0);
  expect(rows.length, 'ligne stock du mug').toBe(1);
  expect(Number(rows[0].product_reserved_quantity)).toBe(1);
});
