// Issue #41036 : erreur 500 à la création d'un client en BO quand le prénom / nom ne contient
// qu'un espace. Vérifie que l'enregistrement du formulaire « Ajouter un client » avec
// prénom = nom = " " renvoie le formulaire avec un message de validation (pas d'erreur 500)
// et qu'aucun client n'est créé.
const { test, expect } = require('@playwright/test');

test('prénom/nom composés d’un espace : erreur de validation, pas de 500', async ({ page }) => {
  await page.goto('/admin-dev/index.php?controller=AdminCustomers&addcustomer');
  if (page.url().includes('security/compromised')) await page.locator('a:has-text("Oui")').first().click();
  await expect(page).toHaveURL(/customers\/new/);
  await page.fill('#customer_first_name', ' ');
  await page.fill('#customer_last_name', ' ');
  await page.fill('#customer_email', 'oracle41036@example.com');
  await page.fill('#customer_password', 'apifhqfpia576854687857');
  const [res] = await Promise.all([
    page.waitForResponse(r => r.url().includes('customers/new') && r.request().method() === 'POST'),
    page.locator('#save-button').click(),
  ]);
  expect(res.status(), 'réponse de l’enregistrement').toBeLessThan(500);
  await page.waitForLoadState();
  await expect(page).toHaveURL(/customers\/new/);
  await expect(page.locator('#customer_first_name')).toBeVisible();
  await expect(page.getByText('Ce champ ne peut pas être vide').first()).toBeVisible();
});
