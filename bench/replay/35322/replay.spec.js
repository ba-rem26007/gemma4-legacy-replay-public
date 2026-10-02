// Issue : historique de commande (FO), le coût de livraison du tableau transporteur est affiché HT
// alors que le client est en affichage TTC (8,40 € attendu, 7,00 € affiché).
const { test, expect } = require('@playwright/test');

test('frais de port TTC dans le détail de commande', async ({ page }) => {
  await page.goto('/fr/index.php?controller=authentication');
  await page.fill('#login-form input[name="email"]', 'pub@prestashop.com');
  await page.fill('#login-form input[name="password"]', '123456789');
  await page.click('#submit-login');
  await expect(page.locator('a.logout').first()).toBeAttached();

  await page.goto('/fr/index.php?controller=order-detail&id_order=1');
  const shipping = page.locator('#order-history ~ * table, .shipping-lines, table:has(th:has-text("Transporteur"))').last();
  await expect(shipping).toContainText(/Frais d'expédition\s+8,40/);
});
