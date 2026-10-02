// Issue #42004 : dupliquer un produit dont le nom est en cyrillique (84 caractères mais 160 octets)
// doit réussir : le nom « copie de … » ne dépasse pas 128 CARACTÈRES et ne doit donc pas être
// tronqué au milieu d'un caractère multi-octets (avant : erreur « Invalid Product localized
// property "name" »). Données : setup.sql (produit 942004).
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');

const PORT = process.env.PS_PORT || '8081';
const N = Number(PORT) - 8080;
const PROJ = `psbench${N === 1 ? '' : N}`;
const sql = q => execSync(`docker exec -i ${PROJ}-db-1 mysql -padmin --default-character-set=utf8mb4 -N prestashop`, { input: q, stdio: ['pipe', 'pipe', 'ignore'] }).toString().trim();
const NAME = 'Персональна електронна обчислювальна машина АРМ фінансової та статистичної звітності';

test('duplication d’un produit au nom cyrillique', async ({ page }) => {
  await page.goto('/admin-dev/');
  const href = await page.locator('a[href*="sell/catalog/products"]').first().getAttribute('href');
  const tok = href.match(/_token=([^&#]+)/)[1];
  await page.goto(`/admin-dev/sell/catalog/products/942004/edit?_token=${tok}`);

  const dup = page.locator('#product_footer_actions_duplicate_product');
  await page.locator('#product_footer_actions_dropdown').click();
  await dup.click();
  await page.locator('.modal.show .btn-confirm-submit').click();
  await page.waitForLoadState('load');

  expect((await page.locator('.alert-danger').allInnerTexts()).map(t => t.replace(/\s+/g, ' ').trim())).toEqual([]);
  await expect(page).toHaveURL(/\/sell\/catalog\/products\/(?!942004\/)\d+\/edit/);
  const copies = sql(`SELECT COUNT(DISTINCT id_product) FROM ps_product_lang WHERE id_lang=1 AND name LIKE '% ${NAME}' AND id_product<>942004`);
  expect(copies, 'produit dupliqué en base').toBe('1');
  await expect(page.locator('input[name="product[header][name][1]"]')).toHaveValue(new RegExp(`${NAME}$`));
});
