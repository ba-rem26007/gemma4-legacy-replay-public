// Issue #41923 : multiboutique avec stock partagé (groupe de boutiques share_stock=1) —
// modifier le « comportement en rupture de stock » d'un produit à déclinaisons doit mettre à jour
// TOUTES ses lignes stock_available (produit ET déclinaisons, id_shop=0 / id_shop_group=1),
// pas seulement la ligne id_product_attribute=0.
// Données : setup.sql (2 boutiques, stock partagé, produit 941923 à 2 déclinaisons, out_of_stock=2).
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');

const PORT = process.env.PS_PORT || '8081';
const N = Number(PORT) - 8080;
const PROJ = `psbench${N === 1 ? '' : N}`;
const sql = q => execSync(`docker exec -i ${PROJ}-db-1 mysql -padmin -N prestashop`, { input: q, stdio: ['pipe', 'pipe', 'ignore'] }).toString().trim();
const outOfStock = () => sql('SELECT GROUP_CONCAT(CONCAT(id_product_attribute, ":", out_of_stock) ORDER BY id_product_attribute) FROM ps_stock_available WHERE id_product=941923');

test.afterAll(() => {
  sql("UPDATE ps_shop_group SET share_stock=0 WHERE id_shop_group=1; UPDATE ps_configuration SET value='0' WHERE name='PS_MULTISHOP_FEATURE_ACTIVE';");
});

test('stock partagé : refuser les commandes en rupture pour toutes les déclinaisons', async ({ page }) => {
  expect(outOfStock()).toBe('0:2,941923:2,941924:2');

  await page.goto('/admin-dev/');
  await page.goto(page.url() + '&setShopContext=s-1');
  const href = await page.locator('a[href*="sell/catalog/products"]').first().getAttribute('href');
  const tok = href.match(/_token=([^&#]+)/)[1];
  await page.goto(`/admin-dev/sell/catalog/products/941923/edit?_token=${tok}`);

  await page.locator('a[href="#product_combinations-tab"]').click();
  await page.locator('#product_combinations_availability_out_of_stock_type_0').check({ force: true });
  const save = page.locator('#product_footer_save');
  await expect(save).toBeEnabled();
  await Promise.all([page.waitForNavigation({ timeout: 30000 }).catch(() => {}), save.click()]);
  await page.waitForLoadState('load');
  await expect.poll(() => sql('SELECT out_of_stock FROM ps_stock_available WHERE id_product=941923 AND id_product_attribute=0'), { timeout: 20000 }).toBe('0');

  expect(outOfStock()).toBe('0:0,941923:0,941924:0');
});
