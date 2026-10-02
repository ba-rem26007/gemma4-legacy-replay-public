// Test de reproduction écrit par Gemma (gemma-4-31b-it) à partir du TICKET SEUL — PR #41524
const { test, expect } = require('@playwright/test');
const { execSync: __x } = require('child_process');
const __P = 'psbench' + ((process.env.PS_PORT||'8081')==='8081' ? '' : String(Number(process.env.PS_PORT)-8080));
test.beforeAll(() => { __x(`docker exec -i ${__P}-db-1 mysql -padmin prestashop`, { input: "-- Assurer que le module de paiement par virement est activ\u00e9\nUPDATE ps_module SET active = 1 WHERE name = 'ps_wirepayment';" }); });
// Issue: The payment method is not printed on the invoice if the order isn't yet paid.
// This test creates an order using "Bank Wire" (Virement bancaire), which leaves the order in a "not yet paid" status.
// It then generates the invoice in the Back Office and verifies that the payment method name is present in the PDF.


test('le mode de paiement est imprimé sur la facture même si la commande n\'est pas encore payée', async ({ page }) => {
  // 1. FO: Création d'une commande non payée (Virement bancaire)
  await page.goto('/connexion');
  await page.fill('input[name="email"]', 'pub@prestashop.com');
  await page.fill('input[name="password"]', '123456789');
  await page.click('button[type="submit"]');

  await page.goto('/index.php?id_product=1&controller=product');
  await page.click('[data-button-action="add-to-cart"]');

  // Aller directement au checkout pour éviter les 404 sur /cart
  await page.goto('/checkout/checkout');
  
  // Naviguer dans le tunnel de commande jusqu'à la page de paiement
  // On clique sur le bouton de validation principal tant que le mode de paiement "bankwire" n'est pas visible
  while (!(await page.locator('input[value="bankwire"]').isVisible())) {
    const submitBtn = page.locator('button[type="submit"], .continue, text=Continuer, text=Commander');
    if (await submitBtn.count() === 0) {
      // Si on est bloqué, on tente de forcer le passage à l'étape suivante via l'URL si possible, 
      // mais normalement le bouton submit est présent.
      break;
    }
    await submitBtn.first().click();
    await page.waitForLoadState('networkidle');
  }

  // Sélection du virement bancaire (commande non payée)
  await page.locator('input[value="bankwire"]').check();
  await page.click('button[type="submit"]');

  // 2. BO: Génération de la facture
  await page.goto('/admin-dev/');
  
  // Navigation vers la liste des commandes
  await page.click('a[href*="controller=AdminOrders"]');
  
  // Sélection de la commande fraîchement créée (la première de la liste)
  await page.click('td a[href*="id_order="]');
  
  // Aller dans l'onglet Documents
  await page.click('text=Documents');
  
  // Intercepter la réponse du PDF de la facture
  const responsePromise = page.waitForResponse(r => r.url().includes('action=generateInvoice'));
  await page.click('text=Générer la facture');
  const response = await responsePromise;
  
  const buffer = await response.body();
  const pdfContent = buffer.toString();

  // Assertion : Le mode de paiement "Virement" doit apparaître dans le contenu du PDF
  // On utilise un terme large pour éviter la fragilité des libellés exacts
  expect(pdfContent).toContain('Virement');
});
