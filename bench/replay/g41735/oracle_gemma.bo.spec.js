// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #41735, validé pre/post automatiquement
// Issue: Custom feature values are listed in the default features list in the Back Office.
// The fix adds a filter to exclude values where 'custom' is 1 in both FeatureQueryBuilder and FeatureValueQueryBuilder.
const { test, expect } = require('@playwright/test');

test('custom feature values should not be displayed in the default values list', async ({ page }) => {
  // Navigate to the feature values list for the specific feature
  await page.goto('/admin-dev/catalog/features/values?featureId=9999');

  // Handle the security warning page if it appears
  if (page.url().includes('security/compromised')) {
    await page.getByText(/comprends les risques|understand the risks/i).click();
  }

  // Ensure we are on the correct page and the grid has loaded
  await expect(page).toHaveURL(/catalog\/features\/values/);
  const grid = page.locator('table');
  await expect(grid).toBeVisible({ timeout: 10000 });

  // The default value (custom=0) must be visible
  await expect(grid).toContainText('Default Value');

  // The custom value (custom=1) must NOT be visible.
  // Before the fix, the query did not filter by 'custom = 0', so 'Custom Value 123' would appear.
  await expect(grid).not.toContainText('Custom Value 123');
});
