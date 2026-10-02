// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37944, validé pre/post automatiquement
// Issue: MaterialChoiceTableType does not render help text.
// The "Group access" (Accès groupe) field in the Category edit page uses this type.
// We verify that the help text associated with this field is rendered in the DOM.
const { test, expect } = require('@playwright/test');

test('le texte d\'aide du champ Accès groupe est affiché dans la modification d\'une catégorie', async ({ page }) => {
  await page.goto('/admin-dev/');

  // Gestion de la page de sécurité Symfony (token)
  const riskButton = page.getByText(/comprends les risques|understand the risks/i);
  if (await riskButton.isVisible()) {
    await riskButton.click();
  }

  // Navigation vers Catalogue > Catégories
  // On utilise des sélecteurs basés sur l'URL pour éviter les problèmes de libellés avec icônes
  // On clique d'abord sur le menu parent "Catalogue" pour déployer le sous-menu
  await page.locator('a[href*="sell/catalog"]').first().click();
  
  // On clique sur le lien "Catégories"
  await page.locator('a[href*="categories"]').first().click();

  // On clique sur le lien "Modifier" de la première catégorie de la liste
  await page.locator('a:has-text("Modifier")').first().click();

  // Le champ "Accès groupe" est un MaterialChoiceTableType.
  // Le texte d'aide attendu contient "groupes de clients".
  // On cherche un élément de texte d'aide (classe help-block, form-text ou help) contenant ce motif.
  const helpText = page.locator('.help-block, .form-text, .help').filter({ 
    hasText: /groupes de clients/i 
  });
  
  // Avant le correctif, le bloc {{ block('form_help') }} était absent du template Twig, 
  // donc l'élément n'est pas rendu dans le DOM.
  await expect(helpText).toBeVisible();
});
