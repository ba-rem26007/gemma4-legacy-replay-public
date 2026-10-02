// Issue #41299 : une URL produit invalide (id inexistant) provoque une erreur fatale
// dans ProductController::assignPriceAndTax() au lieu d'afficher la page 404.
// Vérifie que la page renvoie un 404 AVEC la page d'erreur du thème rendue
// (message « Ce produit n'est plus disponible »), et non une réponse vide.
const { test, expect } = require('@playwright/test');

for (const id of ['99999']) {
  test(`produit inexistant id=${id} → page 404 rendue`, async ({ page }) => {
    const res = await page.goto(`/index.php?id_product=${id}&controller=product`);
    expect(res.status()).toBe(404);
    const body = await res.text();
    expect(body.length, 'corps de réponse vide (erreur fatale)').toBeGreaterThan(1000);
    await expect(page.locator('body')).toContainText(/n.est plus disponible/i);
  });
}
