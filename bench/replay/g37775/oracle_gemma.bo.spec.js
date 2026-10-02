// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37775, validé pre/post automatiquement
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

test('opening a .webp attachment in customer service does not throw an exception', async ({ page }) => {
  // Create a dummy webp file for upload
  const filePath = path.join(__dirname, 'test_attachment.webp');
  fs.writeFileSync(filePath, 'dummy webp content');

  // 1. FO: Send a contact message with a .webp attachment
  // The snapshot shows the contact page is at /nous-contacter
  await page.goto('/nous-contacter');
  
  // Use standard PrestaShop contact form field names
  await page.fill('input[name="email"]', 'test@example.com');
  await page.fill('textarea[name="message"]', 'This is a test message with a webp file.');
  await page.setInputFiles('input[type="file"]', filePath);
  await page.click('button[type="submit"]');
  
  // Wait for the form to be submitted
  await page.waitForLoadState('networkidle');

  // 2. BO: Navigate to Customer Service
  await page.goto('/admin-dev/');
  
  // Handle the "understand the risks" security page if it appears
  const riskWarning = page.locator('text=/comprends les risques|understand the risks/i');
  if (await riskWarning.isVisible()) {
    await riskWarning.click();
  }

  // Navigate to Customer Threads (Service Client)
  const customerServiceLink = page.locator('a[href*="controller=AdminCustomerThreads"]').first();
  await customerServiceLink.click();

  // 3. Open the latest thread
  // The first link in the table that leads to the thread view
  const firstThreadLink = page.locator('a[href*="viewcustomer_thread"]').first();
  await firstThreadLink.click();

  // 4. Click on the .webp attachment
  // The link to the file contains 'filename=' and we filter for .webp extension
  const attachmentLink = page.locator('a[href*="filename="]').filter({ hasText: /\.webp$/i }).first();
  
  // If the bug is present, the server calls die(Tools::displayError('Invalid file extension.'));
  await attachmentLink.click();

  // Assertion: The page should not contain the specific error message thrown by the bug
  await expect(page.locator('body')).not.toContainText('Invalid file extension');
  
  // Cleanup
  if (fs.existsSync(filePath)) {
    fs.unlinkSync(filePath);
  }
});
