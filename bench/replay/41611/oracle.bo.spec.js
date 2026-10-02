// Issue #41611 : la recherche « Compatibilité avec les autres règles panier » (onglet Conditions
// d'une NOUVELLE règle panier) ne filtre pas : toutes les règles sont renvoyées.
// setup.sql crée 3 règles (« 10% off », « -50% summer », « Free shipping »).
// Vérifie qu'en tapant « 10 » dans le champ de recherche de la liste des règles compatibles,
// seule « 10% off » reste proposée.
const { test, expect } = require('@playwright/test');

test('filtre de recherche des règles panier compatibles (nouvelle règle)', async ({ page }) => {
  await page.goto('/admin-dev/index.php?controller=AdminCartRules&addcart_rule');
  if (page.url().includes('security/compromised')) await page.locator('a:has-text("Oui")').first().click();
  const list = page.locator('#cart_rule_select_2');
  // chargement initial des listes (AJAX loadCartRules)
  await expect(list.locator('option', { hasText: 'Free shipping' })).toHaveCount(1);
  await page.locator('#cart_rule_link_conditions').click();
  const restriction = page.locator('#cart_rule_restriction');
  if (!(await restriction.isChecked())) await restriction.check();
  const filter = page.locator('#cart_rule_select_2_filter');
  await expect(filter).toBeVisible();
  const resp = page.waitForResponse(r => r.url().includes('action=loadCartRules') && r.url().includes('search=10'));
  await filter.pressSequentially('10');
  await resp;
  await expect(list.locator('option', { hasText: '10% off' })).toHaveCount(1);
  await expect(list.locator('option', { hasText: 'summer' })).toHaveCount(0);
  await expect(list.locator('option', { hasText: 'Free shipping' })).toHaveCount(0);
});
