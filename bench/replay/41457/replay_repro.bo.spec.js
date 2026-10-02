// Test de reproduction écrit par Gemma (gemma-4-31b-it) à partir du TICKET SEUL — PR #41457
const { test, expect } = require('@playwright/test');
// Issue: The prefix and the invoice number are missing from the name of the invoice PDFs, only the year appears.
// The filename should be like FA2024-00001.pdf instead of 2024.pdf.


test('le nom du fichier PDF de la facture doit contenir le préfixe et le numéro, pas seulement l\'année', async ({ page }) => {
  const year = new Date().getFullYear().toString();

  // Navigation BO et gestion de la page de sécurité
  await page.goto('/admin-dev/');
  if (page.url().includes('security/compromised')) {
    await page.locator('a', { hasText: /comprends les risques|understand the risks/i }).first().click();
  }

  // Accéder à la liste des factures
  // On s'assure que le menu "Commandes" est ouvert, puis on clique sur "Factures"
  const menuCommandes = page.getByRole('link', { name: /Commandes/i }).first();
  await menuCommandes.click();
  
  const linkFactures = page.getByRole('link', { name: 'Factures' });
  await linkFactures.click();
  
  // Attendre la navigation vers la page des factures
  await page.waitForURL(/invoices/);

  // On cherche le premier lien de téléchargement PDF disponible dans la liste
  // Le sélecteur [href*="pdf"] est robuste pour les liens de génération de PDF en BO
  const pdfLink = page.locator('a[href*="pdf"]').first();
  
  // On attend que le lien soit visible pour s'assurer que le tableau est chargé
  await expect(pdfLink).toBeVisible({ timeout: 15000 });

  // Téléchargement du fichier
  const [download] = await Promise.all([
    page.waitForEvent('download'),
    pdfLink.click(),
  ]);

  const filename = download.suggestedFilename();

  // ASSERTION MÉTIER :
  // Le bug : le fichier s'appelle "2024.pdf" (Année.pdf)
  // Attendu : le fichier doit contenir le préfixe et le numéro (ex: FA2024-001.pdf)
  
  // 1. Le nom ne doit pas être strictement égal à "Année.pdf"
  expect(filename, `Le nom du fichier PDF (${filename}) ne doit pas être uniquement l'année (${year}.pdf)`).not.toBe(`${year}.pdf`);
  
  // 2. Le nom doit contenir l'année
  expect(filename, `Le nom du fichier (${filename}) doit contenir l'année ${year}`).toContain(year);
  
  // 3. Le nom doit être plus long que "YYYY.pdf" pour prouver la présence du numéro de facture
  expect(filename.length, `Le nom du fichier (${filename}) est trop court pour contenir le numéro de facture`).toBeGreaterThan(`${year}.pdf`.length);
});
