// Issue #41320 : supprimer d'une commande une ligne dont le produit a été supprimé du catalogue doit
// fonctionner (avant : 500 « Product with ID X could not be loaded », puis 500 Twig au rafraîchissement
// de la liste des produits). setup.sql ajoute à la commande 2 une ligne pour le produit inexistant 99032.
const { test, expect } = require('@playwright/test');

test('suppression d’une ligne de commande dont le produit n’existe plus', async ({ page }) => {
  await page.goto('/admin-dev/');
  const href = await page.locator('a[href*="sell/orders"]').first().getAttribute('href');
  await page.goto(href.replace(/sell\/orders\/?\?/, 'sell/orders/2/view?'));

  const row = page.locator('tr').filter({ has: page.locator('.js-order-product-delete-btn[data-order-detail-id="99032"]') });
  await expect(row).toHaveCount(1);

  page.on('dialog', d => d.accept());
  const seen = [];
  page.on('response', r => { if (/\/sell\/orders\/2\//.test(r.url())) seen.push(`${r.request().method()} ${r.status()} ${r.url().split('?')[0]}`); });
  const del = page.waitForResponse(r => /\/sell\/orders\/2\/products\/99032\/delete/.test(r.url()));
  await row.locator('.js-order-product-delete-btn').click();
  // Modale de confirmation PrestaShop (si présente)
  const confirm = page.locator('.modal.show .btn-confirm-submit, .modal.show button.btn-primary, .modal.show button.btn-danger').first();
  await confirm.click({ timeout: 5000 }).catch(() => {});
  const r = await del;
  expect(r.status()).toBeLessThan(400);

  // La liste des produits est rafraîchie sans erreur et la ligne a disparu
  await expect.poll(() => seen.find(x => /^GET \d+ .*\/sell\/orders\/2\/products$/.test(x)), { timeout: 15000 }).toBeTruthy();
  expect(seen.find(x => /^GET \d+ .*\/sell\/orders\/2\/products$/.test(x))).toMatch(/^GET 200 /);
  await expect(page.locator('.js-order-product-delete-btn[data-order-detail-id="99032"]')).toHaveCount(0);
  await expect(page.locator('.js-order-product-delete-btn[data-order-detail-id="4"]')).toHaveCount(1);
});
