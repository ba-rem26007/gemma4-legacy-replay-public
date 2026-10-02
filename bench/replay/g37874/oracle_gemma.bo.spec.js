// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37874, validé pre/post automatiquement
// Issue: BO > Movements - Search results is undefined and loads forever
// The fix adds missing product/combination identifier columns (ean13, isbn, upc, mpn) 
// to the StockMovementRepository query. Without these, the frontend JS fails to 
// process the response, resulting in an "undefined" notification and infinite loading.
const { test, expect } = require('@playwright/test');

test('la recherche d\'un produit inexistant dans les mouvements de stock ne produit pas d\'erreur undefined', async ({ page }) => {
  // 1. Accès au Back-Office
  await page.goto('/admin-dev/');

  // 2. Navigation vers la page Mouvements de stock
  // On utilise un sélecteur basé sur le texte pour être plus robuste que l'URL exacte
  // On utilise force: true car le lien peut être dans un menu déroulant fermé
  const movementsLink = page.locator('a', { hasText: /Mouvements/i }).first();
  await movementsLink.click({ force: true });

  // Gestion de la page de sécurité Symfony si elle apparaît
  const risksLink = page.locator('text=/comprends les risques|understand the risks/i');
  if (await risksLink.isVisible()) {
    await risksLink.click();
  }

  // 3. Recherche d'un produit qui n'existe pas
  // On attend que le champ de recherche soit disponible
  const searchInput = page.locator('input[name="search"]');
  await expect(searchInput).toBeVisible();
  await searchInput.fill('PRODUIT_INEXISTANT_999');
  
  // On clique sur le bouton de recherche
  await page.locator('button[type="submit"]').first().click();

  // 4. Vérification du comportement
  // On attend la réponse du serveur pour s'assurer que la recherche a été traitée
  await page.waitForResponse(resp => resp.url().includes('movements') && resp.status() === 200);

  // Le bug se manifestait par une notification contenant le texte "undefined"
  // On vérifie que ce texte n'est PAS présent dans les notifications ou alertes
  const notification = page.locator('.notification, .alert, .toast, .text-danger');
  await expect(notification).not.toContainText('undefined');

  // On vérifie que le chargement s'est arrêté (le spinner de chargement disparaît)
  const loader = page.locator('.loading-mask, .spinner, [data-loader]');
  await expect(loader).not.toBeVisible();

  // Assertion métier : on doit voir un message indiquant qu'aucun résultat n'a été trouvé
  // ou que le tableau est rendu (même vide), mais pas un état de chargement infini.
  const noResults = page.locator('text=/aucun résultat|no results|aucun enregistrement/i');
  const table = page.locator('table');
  
  const isNoResultsVisible = await noResults.isVisible();
  const isTableVisible = await table.isVisible();
  expect(isNoResultsVisible || isTableVisible).toBe(true);
});
