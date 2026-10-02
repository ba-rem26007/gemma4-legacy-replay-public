// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #38482, validé pre/post automatiquement
// Issue: La facture d'un produit virtuel ne doit pas afficher le transporteur.
// Le correctif ajoute une condition {if !$order->isVirtual()} autour du tableau du transporteur dans le template PDF.
const { test, expect } = require('@playwright/test');

test('la facture d\'une commande virtuelle ne doit pas afficher le transporteur', async ({ page }) => {
  await page.goto('/admin-dev/');

  // Fonction pour gérer la page de sécurité Symfony si elle apparaît
  const handleSecurity = async () => {
    if (await page.locator('text=/comprends les risques|understand the risks/i').isVisible()) {
      await page.locator('text=/comprends les risques|understand the risks/i').click();
    }
  };

  // 1. Aller dans la liste des commandes
  await page.locator('a').filter({ hasText: /Commandes/i }).first().click();
  await handleSecurity();

  // 2. Ouvrir la commande 1
  await page.locator('a').filter({ hasText: /#1/ }).first().click();
  await handleSecurity();

  // 3. Récupérer l'URL de la facture PDF
  const invoiceLink = await page.locator('a[href*="action=pdf"]').first().getAttribute('href');
  const invoiceUrl = invoiceLink.startsWith('http') ? invoiceLink : `/admin-dev/${invoiceLink}`;

  // 4. Télécharger le contenu du PDF
  // On utilise page.request pour récupérer le flux binaire du PDF
  const response = await page.request.get(invoiceUrl);
  expect(response.status()).toBe(200);
  
  const buffer = await response.body();
  const content = buffer.toString('utf8');

  // 5. Assertion métier : le libellé "Transporteur" (Carrier en FR) ne doit pas être présent dans le PDF
  // Avant le correctif, le tableau <table id="shipping-tab"> est rendu même pour les produits virtuels.
  expect(content).not.toMatch(/Transporteur/i);
});
