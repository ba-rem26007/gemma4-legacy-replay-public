// Issue #35888 : une fois la quantité minimale atteinte dans le panier,
// la fiche produit doit proposer 1 (et non plus 3).
const { test, expect } = require('@playwright/test');

const PRODUCT = '/fr/index.php?id_product=6&controller=product';

test('qté minimale ramenée à 1 quand déjà atteinte dans le panier', async ({ page }) => {
  await page.goto(PRODUCT);
  const qty = page.locator('#quantity_wanted');
  await expect(qty).toHaveValue('3');

  // Ajout de 3 produits au panier
  await page.locator('[data-button-action="add-to-cart"]').click();
  await expect(page.locator('#blockcart-modal')).toBeVisible();

  // Retour sur la fiche produit : la quantité proposée doit être 1
  await page.goto(PRODUCT);
  await expect(qty).toHaveValue('1');
});
