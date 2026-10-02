// Issue #40743 : avec les factures désactivées, passer une commande à un statut « payé »
// (Paiement accepté) doit enregistrer un paiement sur la commande (bloc Paiement de la fiche commande BO).
const { test, expect } = require('@playwright/test');

test('paiement enregistré au passage à « Paiement accepté » avec factures désactivées', async ({ page }) => {
  await page.goto('/admin-dev/');
  const href = await page.locator('a[href*="sell/orders"]').first().getAttribute('href');
  await page.goto(href.replace(/sell\/orders\/?\?/, 'sell/orders/5/view?'));
  const header = page.locator('#view_order_payments_block .card-header-title');
  await expect(header).toContainText('(0)');

  // Changement de statut via le formulaire du haut de page
  await page.locator('#update_order_status_action_input').selectOption('2'); // 2 = Paiement accepté (paid = 1)
  await Promise.all([
    page.waitForNavigation(),
    page.locator('#update_order_status_action_btn').click(),
  ]);
  await expect(page.locator('#update_order_status_action_input')).toHaveValue('2');

  // Un paiement doit avoir été enregistré
  await expect(header).toContainText('(1)');
  await expect(page.locator('#view_order_payments_block [data-role="payments-grid-table"] tbody tr:not(.d-print-none)').filter({ hasText: '€' })).toHaveCount(1);
});
