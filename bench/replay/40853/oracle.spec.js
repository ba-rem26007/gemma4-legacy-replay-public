// Issue #40853 : recherche floue (PS_SEARCH_FUZZY) quand le mot le plus proche contient une apostrophe (s'u).
// Le mot proche doit être échappé/normalisé (getSearchParamFromWord) : la requête ne doit pas casser
// (erreur SQL 1064) et la recherche « shu » doit retrouver le produit indexé avec « s'u » (produit 1).
const { test, expect } = require('@playwright/test');

test('recherche floue avec mot proche contenant une apostrophe', async ({ page }) => {
  const resp = await page.goto('/recherche?controller=search&s=shu');
  expect(resp.status()).toBe(200);
  await expect(page.locator('.product-miniature[data-id-product="1"]')).toHaveCount(1);
});
