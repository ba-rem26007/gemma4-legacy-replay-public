// Issue #35280 : Catalogue > Stock, la recherche avec 2 mots-clés ne renvoie plus aucun produit.
const { test, expect } = require('@playwright/test');

test('recherche stock avec deux mots-clés', async ({ page }) => {
  await page.goto('/admin-dev/index.php?controller=AdminDashboard');
  const href = await page.locator('a[href*="sell/stocks"]').first().getAttribute('href');
  await page.goto(href);
  await page.waitForResponse(r => r.url().includes('/api/stocks/'));

  const search = page.locator('.tags-input input, input.form-control.input[placeholder=""]').first();
  await search.fill('mug');
  await search.press('Enter');
  await page.waitForResponse(r => r.url().includes('/api/stocks/') && r.url().includes('mug'));
  const res = page.waitForResponse(r => r.url().includes('/api/stocks/') && r.url().includes('best'));
  await search.fill('best');
  await search.press('Enter');
  const r = await res;
  const body = await r.json().catch(() => ({}));
  expect(r.status()).toBe(200);
  // « mug » ET « best » → Mug The best is yet to come
  expect(body.data?.data?.map(p => p.product_name)).toContain('Mug The best is yet to come');
});
