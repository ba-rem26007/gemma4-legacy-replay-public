// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #38027, validé pre/post automatiquement
// Issue #BO export - Cannot export subcategories
// Le bouton d'exportation depuis la vue d'une catégorie parente doit exporter 
// les sous-catégories de celle-ci et non l'ensemble des catégories.
const { test, expect } = require('@playwright/test');

test('l export de catégories depuis une sous-catégorie inclut bien les enfants de celle-ci', async ({ page }) => {
  // 1. Navigation robuste via le menu pour récupérer le token Symfony
  await page.goto('/admin-dev/');
  
  const categoriesLink = page.locator('a:has-text("Catégories")').first();
  const href = await categoriesLink.getAttribute('href');
  
  // On transforme l'URL de la liste (/categories) en URL de la catégorie 3 (/categories/3)
  // tout en conservant le token présent dans le href
  const targetUrl = href.replace('/categories', '/categories/3');
  await page.goto(targetUrl);

  // 2. Gestion de la page de sécurité Symfony si elle apparaît malgré le token
  const riskWarning = page.locator('text=/comprends les risques|understand the risks/i');
  if (await riskWarning.isVisible()) {
    await riskWarning.click();
  }

  // 3. Interception de la réponse du téléchargement CSV
  // Le correctif modifie la route pour inclure le categoryId
  const responsePromise = page.waitForResponse(r => 
    r.url().includes('/categories/export') && r.status() === 200
  );

  // Le bouton d'export est un lien contenant "export" dans l'URL
  await page.locator('a[href*="export"]').click();

  const response = await responsePromise;
  const csvContent = await response.text();

  // Assertion métier : le CSV doit contenir le nom de la sous-catégorie créée en setup.sql
  // Si le bug est présent, l'export ignore le contexte de la catégorie parente (id=3)
  expect(csvContent).toContain('SUB_CATEGORY_EXPORT_TEST');
});
