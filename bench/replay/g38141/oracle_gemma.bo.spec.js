// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #38141, validé pre/post automatiquement
// Issue: Module configuration language switches throw javascript error, missing variable.
// The variable 'changeFormLanguageUrl' was missing on Symfony-migrated pages, 
// causing changeEmployeeLanguage() to throw a ReferenceError.
const { test, expect } = require('@playwright/test');

test('la variable changeFormLanguageUrl est définie sur la page de configuration d\'un module', async ({ page }) => {
  await page.goto('/admin-dev/');

  // Navigation vers le gestionnaire de modules
  await page.getByRole('link', { name: /Modules/i }).click();

  // Fonction pour gérer l'alerte de sécurité qui peut bloquer l'UI
  const handleRiskWarning = async () => {
    const warning = page.getByText(/comprends les risques|understand the risks/i);
    if (await warning.isVisible()) {
      await warning.click();
    }
  };

  await handleRiskWarning();

  // On attend que le lien "Configurer" soit visible et cliquable
  const configLink = page.getByRole('link', { name: /Configurer/i }).first();
  await configLink.waitFor({ state: 'visible', timeout: 15000 });
  
  // On clique sur le lien de configuration
  await configLink.click();

  // On gère l'alerte de sécurité qui apparaît souvent après l'accès à une page de configuration
  await handleRiskWarning();

  // Le test vérifie que la variable globale 'changeFormLanguageUrl' est injectée dans le JS.
  // Avant le correctif, cette variable était absente sur les pages Symfony, provoquant un crash de changeEmployeeLanguage().
  const isDefined = await page.evaluate(() => {
    return typeof changeFormLanguageUrl !== 'undefined';
  });

  expect(isDefined).toBe(true);
});
