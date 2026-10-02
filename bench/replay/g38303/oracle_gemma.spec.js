// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #38303, validé pre/post automatiquement
// Issue: Deprecated warnings (strpos, constant) appearing on FO product pages on first load.
// The fix involves updating the classic theme and registering 'strpos' as a Smarty modifier.
// The test verifies that no PHP deprecation warnings containing 'strpos' or 'constant' 
// are visible on the product page.
const { test, expect } = require('@playwright/test');

test('absence de warnings de dépréciation sur la page produit', async ({ page }) => {
  // On se rend sur la page d'un produit (le produit 1 est présent dans les données de démo)
  await page.goto('/index.php?id_product=1&controller=product');

  // On vérifie que le corps de la page ne contient pas de message de dépréciation PHP.
  // On utilise une regex pour détecter "Deprecated" associé aux fonctions citées dans le ticket.
  // Le test échouera si "Deprecated: ... strpos" ou "Deprecated: ... constant" est présent.
  const bodyText = await page.locator('body').innerText();
  expect(bodyText, 'La page ne doit pas afficher de warnings de dépréciation (strpos/constant)').not.toMatch(/Deprecated:.*(strpos|constant)/i);
});
