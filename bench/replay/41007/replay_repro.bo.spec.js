// Test de reproduction écrit par Gemma (gemma-4-31b-it) à partir du TICKET SEUL — PR #41007
const { test, expect } = require('@playwright/test');
// Issue: CountryQueryBuilder::getCountQueryBuilder() always returns 1 instead of the true total.
// This causes the Back Office country grid to display an incorrect total number of items.

const { execSync } = require('child_process');

const PROJ = 'psbench' + ((process.env.PS_PORT || '8081') === '8081' ? '' : String(Number(process.env.PS_PORT) - 8080));
const sql = q => execSync(`docker exec ${PROJ}-db-1 mysql -padmin prestashop -N -e "${q}"`).toString();

test('the country grid should display the correct total count of countries', async ({ page }) => {
  // 1. Get the actual number of countries from the database
  const dbCount = parseInt(sql('SELECT COUNT(*) FROM ps_country'), 10);
  expect(dbCount).toBeGreaterThan(1);

  // 2. Navigate to the Countries page in the Back Office
  await page.goto('/admin-dev/index.php?controller=AdminCountries');
  
  // Handle the security warning if it appears
  if (page.url().includes('security/compromised')) {
    await page.locator('a:has-text("Oui"), a:has-text("understand the risks")').first().click();
  }

  // Ensure we are on the countries grid page
  await expect(page).toHaveURL(/.*countries.*/);

  // 3. Check the pagination/total count in the grid
  // In PrestaShop Symfony grids, the total count is typically displayed in the pagination area
  // (e.g., "1-50 of 241" or "Showing 1 to 50 of 241 entries")
  const pagination = page.locator('.pagination');
  await expect(pagination).toBeVisible();

  // The test fails if the bug is present because the UI will show "1" instead of the dbCount
  // We use a word boundary regex to ensure we match the exact number
  await expect(pagination).toContainText(new RegExp(`\\b${dbCount}\\b`));
});
