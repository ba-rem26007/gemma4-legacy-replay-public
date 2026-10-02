// Test de reproduction écrit par Gemma (gemma-4-31b-it) à partir du TICKET SEUL — PR #41225
const { test, expect } = require('@playwright/test');
const { execSync: __x } = require('child_process');
const __P = 'psbench' + ((process.env.PS_PORT||'8081')==='8081' ? '' : String(Number(process.env.PS_PORT)-8080));
test.beforeAll(() => { __x(`docker exec -i ${__P}-db-1 mysql -padmin prestashop`, { input: "-- On identifie deux attributs du groupe 'Couleur' utilis\u00e9s par le produit 1 (Hummingbird).\n-- On force l'attribut avec l'ID le plus \u00e9lev\u00e9 \u00e0 la position 1 (COLOR_POS1)\n-- et l'attribut avec l'ID le plus bas \u00e0 la position 2 (COLOR_POS2).\n-- Si le bug est pr\u00e9sent, le tri se fera par ID (ou id_product_attribute), \n-- et COLOR_POS2 appara\u00eetra donc avant COLOR_POS1.\n\nUPDATE ps_attribute SET position = 1 WHERE id_attribute = (\n    SELECT id_attribute FROM (\n        SELECT a.id_attribute FROM ps_product_attribute_combination pac \n        JOIN ps_attribute a ON pac.id_attribute = a.id_attribute \n        JOIN ps_product_attribute pa ON pac.id_product_attribute = pa.id_product_attribute \n        WHERE pa.id_product = 1 AND a.id_attribute_group = (SELECT id_attribute_group FROM ps_attribute_group_lang WHERE public_name = 'Couleur' LIMIT 1) \n        ORDER BY a.id_attribute DESC LIMIT 1\n    ) as t\n);\n\nUPDATE ps_attribute SET position = 2 WHERE id_attribute = (\n    SELECT id_attribute FROM (\n        SELECT a.id_attribute FROM ps_product_attribute_combination pac \n        JOIN ps_attribute a ON pac.id_attribute = a.id_attribute \n        JOIN ps_product_attribute pa ON pac.id_product_attribute = pa.id_product_attribute \n        WHERE pa.id_product = 1 AND a.id_attribute_group = (SELECT id_attribute_group FROM ps_attribute_group_lang WHERE public_name = 'Couleur' LIMIT 1) \n        ORDER BY a.id_attribute ASC LIMIT 1\n    ) as t\n);\n\nUPDATE ps_attribute_lang SET name = 'COLOR_POS1' WHERE id_attribute = (\n    SELECT id_attribute FROM (\n        SELECT a.id_attribute FROM ps_product_attribute_combination pac \n        JOIN ps_attribute a ON pac.id_attribute = a.id_attribute \n        JOIN ps_product_attribute pa ON pac.id_product_attribute = pa.id_product_attribute \n        WHERE pa.id_product = 1 AND a.id_attribute_group = (SELECT id_attribute_group FROM ps_attribute_group_lang WHERE public_name = 'Couleur' LIMIT 1) \n        ORDER BY a.id_attribute DESC LIMIT 1\n    ) as t\n);\n\nUPDATE ps_attribute_lang SET name = 'COLOR_POS2' WHERE id_attribute = (\n    SELECT id_attribute FROM (\n        SELECT a.id_attribute FROM ps_product_attribute_combination pac \n        JOIN ps_attribute a ON pac.id_attribute = a.id_attribute \n        JOIN ps_product_attribute pa ON pac.id_product_attribute = pa.id_product_attribute \n        WHERE pa.id_product = 1 AND a.id_attribute_group = (SELECT id_attribute_group FROM ps_attribute_group_lang WHERE public_name = 'Couleur' LIMIT 1) \n        ORDER BY a.id_attribute ASC LIMIT 1\n    ) as t\n);" }); });
// Issue #... : Attribute swatches on homepage ignore position ordering
// Le test vérifie que sur la page d'accueil, les variantes de couleur d'un produit
// respectent l'ordre de position défini en BO et non l'ordre de création (ID).


test('les swatches de la page d\'accueil respectent l\'ordre de position des attributs', async ({ page }) => {
  await page.goto('/');

  // On cible le produit "Hummingbird printed t-shirt" dans la section des produits phares
  const product = page.locator('.product-miniature', { hasText: /Hummingbird/i }).first();
  await expect(product).toBeVisible();

  // On récupère les swatches de couleur. 
  // Dans le thème hummingbird, ils sont généralement des éléments avec un attribut 'title'.
  const swatches = product.locator('[title*="COLOR_POS"]');
  
  // On s'assure qu'on a bien nos deux attributs de test
  await expect(swatches).toHaveCount(2);

  // Le premier swatch doit être celui configuré en position 1 (COLOR_POS1)
  // Si le bug est présent, c'est COLOR_POS2 (ID plus bas) qui apparaîtra en premier.
  const firstSwatchTitle = await swatches.first().getAttribute('title');
  expect(firstSwatchTitle, 'Le premier swatch doit correspondre à la position 1').toMatch(/COLOR_POS1/);
});
