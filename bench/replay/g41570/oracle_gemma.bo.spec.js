// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #41570, validé pre/post automatiquement
// Issue: The position column (drag-and-drop handle) is missing in the Features list.
// The fix changes the default 'orderBy' from 'name' to 'position' in FeatureFilters.php.
// In PrestaShop Symfony grids, the position column is only rendered when the grid is ordered by position.
const { test, expect } = require('@playwright/test');

test('the features list should display the position column for reordering', async ({ page }) => {
  // 1. Navigate to Back-Office home to ensure session is active
  await page.goto('/admin-dev/');

  // Handle the "understand the risks" security page if it appears
  const riskButton = page.locator('text=/comprends les risques|understand the risks/i');
  if (await riskButton.isVisible()) {
    await riskButton.click();
  }

  // 2. Navigate to Catalog -> Features via the menu to get the correct Symfony token
  // We wait for the link to be attached to the DOM
  const featuresLink = page.locator('a[href*="catalog/features"]');
  await featuresLink.waitFor({ state: 'visible' });
  await featuresLink.click();

  // Handle the "understand the risks" security page again if it appears after navigation
  const riskButtonAfter = page.locator('text=/comprends les risques|understand the risks/i');
  if (await riskButtonAfter.isVisible()) {
    await riskButtonAfter.click();
  }

  // 3. Verify the presence of the position column
  // The position column in Symfony grids has the class 'grid-position'
  // It is absent when 'orderBy' is 'name' and present when 'orderBy' is 'position'
  const positionHeader = page.locator('th.grid-position');
  await expect(positionHeader).toBeVisible();

  // 4. Verify that the table rows contain the drag handle cells
  const positionCells = page.locator('td.grid-position');
  await expect(positionCells.first()).toBeVisible();
});
