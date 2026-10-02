// Test de reproduction écrit par Gemma (gemma-4-31b-it) à partir du TICKET SEUL — PR #40651
const { test, expect } = require('@playwright/test');
const { execSync: __x } = require('child_process');
const __P = 'psbench' + ((process.env.PS_PORT||'8081')==='8081' ? '' : String(Number(process.env.PS_PORT)-8080));
test.beforeAll(() => { __x(`docker exec -i ${__P}-db-1 mysql -padmin prestashop`, { input: "-- On modifie le groupe d'attributs \"Couleur\" (id=2) pour avoir un nom interne diff\u00e9rent du nom public.\nUPDATE ps_attribute_group_lang SET name = 'Couleur Interne', public_name = 'Couleur Publique' WHERE id_attribute_group = 2 AND id_lang = 1;\n-- On force le stock \u00e0 1 pour la d\u00e9clinaison 1 du produit 1 pour provoquer l'erreur de quantit\u00e9.\nUPDATE ps_stock_available SET quantity = 1, out_of_stock = 0 WHERE id_product = 1 AND id_product_attribute = 1;" }); });
// Issue: Product::getProductName() utilise le nom interne du groupe d'attributs au lieu du nom public.
// Le message d'erreur de quantité dans le panier utilise getProductName() pour afficher le nom complet du produit.


const PRODUCT_URL = '/index.php?id_product=1&id_product_attribute=1&controller=product';

test('le nom du produit dans le message d\'erreur du panier utilise le nom public du groupe d\'attributs', async ({ page }) => {
  // 1. On ajoute une première unité (stock = 1, donc succès)
  await page.goto(PRODUCT_URL);
  const addResponse = page.waitForResponse(r => r.url().includes('cart') && r.request().method() === 'POST');
  await page.locator('[data-button-action="add-to-cart"]').click();
  await addResponse;

  // 2. On tente d'ajouter une deuxième unité via un appel fetch pour récupérer la réponse JSON d'erreur
  // Le message d'erreur retourné par le contrôleur Cart contient le résultat de Product::getProductName()
  await page.goto(PRODUCT_URL);
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

  const errors = (body.errors || []).join(' ');
  
  // Le message doit contenir le nom public et NON le nom interne
  expect(errors).toContain('Couleur Publique');
  expect(errors).not.toContain('Couleur Interne');
});
