// Issue #41570 : Catalogue > Attributs & caractéristiques > onglet Caractéristiques :
// par défaut la liste doit être triée par position et afficher la colonne de poignées
// de déplacement (drag & drop) des positions, comme pour les attributs.
const { test, expect } = require('@playwright/test');

test('grille des caractéristiques : colonne de déplacement des positions', async ({ page }) => {
  await page.goto('/admin-dev/');
  const href = await page.locator('a[href*="attribute-groups"]').first().getAttribute('href');
  await page.goto(href);
  await page.locator('a[href*="/features"]').first().click();
  await expect(page.locator('#feature_grid_table, table#feature_grid_table, .js-grid-table').first()).toBeVisible();
  const grid = page.locator('#feature_grid');
  await expect(grid.locator('tbody tr').first()).toBeVisible();
  // colonne « poignée » de position présente et utilisable dans chaque ligne
  await expect(grid.locator('th[data-column-id="position_handle"]')).toHaveCount(1);
  await expect(grid.locator('tbody td.js-drag-handle').first()).toBeVisible();
});
