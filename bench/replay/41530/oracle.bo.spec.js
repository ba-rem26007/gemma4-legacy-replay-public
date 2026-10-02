// Issue #41530 (BO > Commandes > Paniers) : pour une déclinaison sans image propre,
// l'image du produit n'apparaît pas dans le contenu du panier.
// setup.sql crée une déclinaison (T-shirt colibri XL/Rouge, réf. ORACLE41530) sans image
// associée et le panier n° 41530 qui la contient. Vérifie que la ligne du produit affiche
// une vignette (image de couverture du produit, à défaut d'image de déclinaison).
const { test, expect } = require('@playwright/test');

test('vignette de couverture pour une déclinaison sans image dans la vue panier', async ({ page }) => {
  await page.goto('/admin-dev/index.php?controller=AdminCarts&id_cart=41530&viewcart');
  if (page.url().includes('security/compromised')) await page.locator('a:has-text("Oui")').first().click();
  await expect(page).toHaveURL(/carts\/41530\/view/);
  const row = page.locator('tr', { hasText: 'ORACLE41530' });
  await expect(row).toHaveCount(1);
  const img = row.locator('td').first().locator('img');
  await expect(img).toHaveCount(1);
  await expect(img).toHaveAttribute('src', /\S/);
  // l'image est bien chargée (pas un lien cassé)
  await expect.poll(() => img.evaluate(i => i.complete && i.naturalWidth)).toBeGreaterThan(0);
});
