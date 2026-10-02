// Issue #41225 : l'ordre des attributs hors fiche produit ignore les positions configurées en BO.
// Après avoir placé le groupe « Couleur » avant « Taille » (setup.sql), le lien du produit
// « T-shirt imprimé colibri » sur la page d'accueil (ancre de déclinaison construite par
// Product::getAttributesParams) doit lister les attributs dans l'ordre des positions :
// #/8-couleur-blanc/1-taille-s (et non #/1-taille-s/8-couleur-blanc).
const { test, expect } = require('@playwright/test');

test('ancre de déclinaison dans l’ordre des positions de groupes', async ({ page }) => {
  await page.goto('/');
  const link = page.locator('.product-miniature[data-id-product="1"] a[href*="-hummingbird-printed-t-shirt.html"]').first();
  await expect(link).toBeVisible();
  const href = await link.getAttribute('href');
  expect(href).toContain('#/');
  const anchor = href.split('#')[1];
  expect(anchor).toBe('/8-couleur-blanc/1-taille-s');
});
