// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #38496, validé pre/post automatiquement
// Issue: Mobile - BO - Module manager - The upload module button is not working
// The "upload a module" button on mobile does not trigger the import modal because 
// the necessary data-attributes (data-toggle, data-target) were not assigned to the mobile selector.
const { test, expect } = require('@playwright/test');

test('le bouton d\'importation de module fonctionne sur mobile', async ({ page }) => {
  // 1. Configuration mobile dès le départ pour assurer l'initialisation correcte du DOM/JS
  await page.setViewportSize({ width: 375, height: 667 });

  await page.goto('/admin-dev/');

  // 2. Navigation vers le Gestionnaire de modules
  // D'après le snapshot, le lien "Modules" mène directement au catalogue/gestionnaire
  await page.click('text=/Modules/i');

  // 3. Action : Cliquer sur l'icône "+" (spécifique au mode mobile)
  // On utilise un sélecteur robuste pour le bouton d'ajout rapide
  const addButton = page.locator('text="+"').first();
  await addButton.waitFor({ state: 'visible' });
  await addButton.click();

  // 4. Action : Cliquer sur l'option "Importer un module"
  // On utilise une regex pour être flexible sur le libellé (ex: "Importer un module")
  await page.click('text=/importer/i');

  // 5. Vérification : la modale d'importation doit être visible
  // C'est ici que le test échouait avant le correctif (le clic ne déclenchait rien)
  const importModal = page.locator('#module-modal-import');
  await expect(importModal).toBeVisible();
});
