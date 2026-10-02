// Issue #41007 : CountryQueryBuilder::getCountQueryBuilder() renvoie toujours 1.
// Vérifie que, sur la grille Symfony International > Zones géographiques > Pays
// (feature flag « country » activé par setup.sql), le total affiché dans l'en-tête
// de la grille (« Pays (N) ») correspond au vrai nombre de pays (241 en démo), et non 1.
const { test, expect } = require('@playwright/test');

test('total de la grille Pays = nombre réel de pays', async ({ page }) => {
  // Lien legacy sans jeton : on passe la page « jeton invalide », puis redirection vers la grille Symfony
  await page.goto('/admin-dev/index.php?controller=AdminCountries');
  if (page.url().includes('security/compromised')) await page.locator('a:has-text("Oui")').first().click();
  await expect(page).toHaveURL(/international\/countries/);
  const header = page.locator('#country_grid_panel .card-header-title, #country_grid_panel h3').first();
  await expect(header).toBeVisible();
  const text = await header.innerText();
  const total = Number((text.match(/\((\d+)\)/) || [])[1]);
  expect(total, `en-tête de grille : "${text}"`).toBeGreaterThan(200);
});
