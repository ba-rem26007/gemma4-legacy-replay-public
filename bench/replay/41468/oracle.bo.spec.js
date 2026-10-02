// Issue #41468 : multiboutique — passer un produit à déclinaisons en « Produit standard »
// doit remettre product_shop.cache_default_attribute à 0 pour TOUTES les boutiques
// (avant le correctif : seule la boutique par défaut était remise à 0).
// Données : setup.sql (2 boutiques, produit 941468 à déclinaisons présent dans les 2 boutiques).
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');

const PORT = process.env.PS_PORT || '8081';
const N = Number(PORT) - 8080;
const PROJ = `psbench${N === 1 ? '' : N}`;
const sql = q => execSync(`docker exec -i ${PROJ}-db-1 mysql -padmin -N prestashop`, { input: q, stdio: ['pipe', 'pipe', 'ignore'] }).toString().trim();

test.afterAll(() => {
  sql("UPDATE ps_configuration SET value='0' WHERE name='PS_MULTISHOP_FEATURE_ACTIVE';");
});

test('changement de type -> standard remet cache_default_attribute à 0 partout', async ({ page }) => {
  expect(sql('SELECT GROUP_CONCAT(cache_default_attribute ORDER BY id_shop) FROM ps_product_shop WHERE id_product=941468')).toBe('941468,941468');

  // Contexte boutique 1 (la page produit n'est pas disponible en contexte « toutes boutiques »)
  await page.goto('/admin-dev/');
  await page.goto(page.url() + '&setShopContext=s-1');
  const href = await page.locator('a[href*="sell/catalog/products"]').first().getAttribute('href');
  const tok = href.match(/_token=([^&#]+)/)[1];
  await page.goto(`/admin-dev/index.php/sell/catalog/products/941468/edit?_token=${tok}`);

  await page.getByText('Produit avec déclinaisons').first().click();
  const modal = page.locator('.modal.show');
  await modal.locator('.product-type-choice[data-value="standard"]').click();
  await modal.locator('.btn-confirm-submit').click();
  // 2e confirmation (« Cela supprimera toutes les déclinaisons »)
  const confirm = page.locator('.modal.show .btn-confirm-submit');
  await confirm.click();
  await page.waitForLoadState('load');
  await expect.poll(() => sql('SELECT product_type FROM ps_product WHERE id_product=941468'), { timeout: 20000 }).toBe('standard');

  expect(sql('SELECT GROUP_CONCAT(cache_default_attribute ORDER BY id_shop) FROM ps_product_shop WHERE id_product=941468')).toBe('0,0');
});
