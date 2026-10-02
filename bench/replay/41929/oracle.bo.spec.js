// Issue #41929 : avec la fonctionnalité expérimentale « Catalog price rules » activée,
// la page d'édition d'un produit doit s'ouvrir (avant : exception « Parameter "catalogPriceRuleId"
// for route "admin_catalog_price_rules_edit" must match "\d+" ») et le bloc des règles de prix
// catalogue doit pointer vers les pages Symfony.
// L'exception n'est levée qu'avec des exigences de route strictes, c.-à-d. en mode debug :
// le test active le mode debug (_PS_MODE_DEV_) le temps du test puis le désactive.
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');

const PORT = process.env.PS_PORT || '8081';
const N = Number(PORT) - 8080;
const PROJ = `psbench${N === 1 ? '' : N}`;
const sql = q => execSync(`docker exec -i ${PROJ}-db-1 mysql -padmin -N prestashop`, { input: q, stdio: ['pipe', 'pipe', 'ignore'] }).toString().trim();
const devMode = on => execSync(`docker exec -w /var/www/html ${PROJ}-ps-1 sh -c "sed -i \\"s/define('_PS_MODE_DEV_', ${on ? 'false' : 'true'});/define('_PS_MODE_DEV_', ${on ? 'true' : 'false'});/\\" config/defines.inc.php; rm -rf var/cache/* 2>/dev/null; sleep 1; rm -rf var/cache/* 2>/dev/null; true"`);

test.setTimeout(90_000);
test.beforeAll(() => devMode(true));
test.afterAll(() => {
  devMode(false);
  sql("UPDATE ps_feature_flag SET state=0 WHERE name='catalog_price_rule';");
});

test('édition produit avec le flag catalog_price_rule', async ({ page }) => {
  await page.goto('/admin-dev/');
  const href = await page.locator('a[href*="sell/catalog/products"]').first().getAttribute('href');
  const tok = href.match(/_token=([^&#]+)/)[1];
  const res = await page.goto(`/admin-dev/sell/catalog/products/1/edit?_token=${tok}`);
  expect(await page.title()).not.toContain('must match');
  expect(res.status()).toBeLessThan(400);
  await expect(page.locator('input[name="product[header][name][1]"]').first()).toBeAttached();
  const url = await page.locator('[data-catalog-price-url]').first().getAttribute('data-catalog-price-url');
  expect(url).toContain('/sell/catalog/catalog-price-rules/%catalog_price_rule_id%/edit');
});
