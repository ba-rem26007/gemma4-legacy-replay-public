// Issue #40651 : Product::getProductName() doit utiliser le nom PUBLIC des groupes d'attributs
// (agl.public_name) et non le nom interne. Vérifié via le message d'erreur de quantité du panier
// (« Vous ne pouvez acheter que N "<nom produit : Groupe - valeur>" »), construit avec getProductName().
const { test, expect } = require('@playwright/test');

const PRODUCT = '/fr/index.php?id_product=1&id_product_attribute=1&controller=product';

test('le nom de déclinaison du message panier utilise les noms publics des attributs', async ({ page }) => {
  // 1re unité : acceptée (stock = 1)
  await page.goto(PRODUCT);
  let resp = page.waitForResponse(r => r.url().includes('cart') && r.request().method() === 'POST');
  await page.locator('[data-button-action="add-to-cart"]').click();
  await resp;

  // 2e unité : refusée côté serveur (le bouton est désactivé en JS, on soumet donc le formulaire
  // d'ajout au panier de la page tel quel), le message contient le nom de la déclinaison
  await page.goto(PRODUCT);
  const body = await page.evaluate(async () => {
    const form = document.querySelector('#add-to-cart-or-refresh');
    const data = new FormData(form);
    data.set('add', '1');
    data.set('action', 'update');
    data.set('ajax', '1');
    data.set('qty', '1');
    const r = await fetch(form.action, { method: 'POST', body: data, headers: { Accept: 'application/json' } });
    return r.json();
  });
  const msg = (body.errors || []).join(' ');
  expect(msg).toContain('T-shirt imprimé colibri');
  expect(msg).not.toContain('interne');
  expect(msg).toContain('Taille - ');
});
