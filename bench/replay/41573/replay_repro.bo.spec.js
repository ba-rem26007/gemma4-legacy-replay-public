// Test de reproduction écrit par Gemma (gemma-4-31b-it) à partir du TICKET SEUL — PR #41573
const { test, expect } = require('@playwright/test');
// Issue: The Discount highlight feature no longer exists on Discount V2.
// The test checks if the "Highlight" (Mise en avant) option is present when creating a new discount in the BO.


test('the discount highlight option is displayed in Discount V2', async ({ page }) => {
  await page.goto('/admin-dev/');

  // Handle the security warning page if it appears
  const riskWarning = page.locator('text=/comprends les risques|understand the risks/i');
  if (await riskWarning.isVisible()) {
    await riskWarning.click();
  }

  // 1. Open the "Catalogue" menu. 
  // We use a filter to avoid "Évaluation du catalogue" and target the menu item 
  // which typically contains the "keyboard_arrow_down" icon text in the accessibility tree.
  await page.locator('a:has-text("Catalogue"), a:has-text("Catalog")')
    .filter({ hasText: 'keyboard_arrow_down' })
    .first()
    .click();

  // 2. Click on the "Réductions" (Discounts) sub-menu link.
  // We target the link containing "discounts" in the URL.
  await page.locator('a[href*="discounts"]').first().click();

  // 3. Click on the "Add new discount" button.
  // We use a regex to be robust across languages (Ajouter une nouvelle / Add new).
  await page.locator('a:has-text(/Ajouter une nouvelle|Add new/i)').first().click();

  // 4. The "Highlight" option should be visible on the creation form.
  // We search for a label or text containing "highlight" or "mise en avant" (French translation).
  const highlightOption = page.locator('text=/highlight|mise en avant/i');
  
  // The test fails if the element is not found (bug present) 
  // and passes if the element is visible (bug fixed).
  await expect(highlightOption).toBeVisible();
});
