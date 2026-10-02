// Test de reproduction écrit par Gemma (gemma-4-31b-it) à partir du TICKET SEUL — PR #41320
const { test, expect } = require('@playwright/test');
const { execSync: __x } = require('child_process');
const __P = 'psbench' + ((process.env.PS_PORT||'8081')==='8081' ? '' : String(Number(process.env.PS_PORT)-8080));
test.beforeAll(() => { __x(`docker exec -i ${__P}-db-1 mysql -padmin prestashop`, { input: "-- Au lieu d'un INSERT qui \u00e9choue sur les contraintes NOT NULL, \n-- on modifie une ligne existante de la commande 1 pour qu'elle pointe vers le produit 1.\nUPDATE ps_order_detail SET product_id = 1 WHERE id_order = 1 LIMIT 1;\n\n-- On supprime le produit 1 du catalogue pour reproduire le bug.\nDELETE FROM ps_product_shop WHERE id_product = 1;\nDELETE FROM ps_product WHERE id_product = 1;" }); });
// Issue: Unable to delete product from order when product is deleted from catalog.
// The system throws a 500 error because it tries to load the Product object during deletion.


test('should be able to delete a product from an order even if the product is deleted from catalog', async ({ page }) => {
  // Accès au Back-Office
  await page.goto('/admin-dev/');
  if (await page.getByText(/comprends les risques|understand the risks/i).isVisible()) {
    await page.getByText(/comprends les risques|understand the risks/i).click();
  }

  // Navigation vers la commande 1
  await page.goto('/admin-dev/sell/orders/1');
  if (await page.getByText(/comprends les risques|understand the risks/i).isVisible()) {
    await page.getByText(/comprends les risques|understand the risks/i).click();
  }

  // Gestion du dialogue de confirmation de suppression (si présent)
  page.once('dialog', dialog => dialog.accept());

  // On cible le bouton de suppression du premier produit de la liste
  const deleteBtn = page.locator('button[data-tooltip="Supprimer"], a[title="Supprimer"], .btn-danger').first();
  await expect(deleteBtn).toBeVisible();

  // On intercepte la réponse de la requête de suppression
  const responsePromise = page.waitForResponse(r => 
    r.url().includes('/delete') && r.request().method() === 'POST'
  );

  await deleteBtn.click();
  const response = await responsePromise;

  // Le bug se manifeste par une erreur 500. Le test passe si le statut n'est pas 500.
  expect(response.status()).not.toBe(500);
});
