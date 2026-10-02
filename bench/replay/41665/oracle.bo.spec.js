// Issue #41665 : BO > Commandes > vue commande — dans le bloc Produits, le numéro de facture
// doit utiliser le préfixe dans la langue de l'EMPLOYÉ (FR : « #FA ») et non celle du client
// (EN : « #IN »). Données : setup.sql (commande 941665 en anglais, facture n° 941665).
const { test, expect } = require('@playwright/test');

test('préfixe de facture dans la langue de l’employé', async ({ page }) => {
  await page.goto('/admin-dev/');
  const href = await page.locator('a[href*="sell/orders"]').first().getAttribute('href');
  const tok = href.match(/_token=([^&#]+)/)[1];
  await page.goto(`/admin-dev/sell/orders/941665/view?_token=${tok}`);
  const row = page.locator('#orderProductsTable tbody tr[id^="orderProduct_"], #orderProductsTable tbody tr').first();
  await expect(row.locator('.cellProductTotalPrice')).toBeVisible();
  const invoiceCell = row.locator('td.cellProductTotalPrice + td');
  await expect(invoiceCell).toHaveText('#FA941665');
});
