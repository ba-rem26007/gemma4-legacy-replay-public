// Test de reproduction écrit par Gemma (gemma-4-31b-it) à partir du TICKET SEUL — PR #40971
const { test, expect } = require('@playwright/test');
const { execSync: __x } = require('child_process');
const __P = 'psbench' + ((process.env.PS_PORT||'8081')==='8081' ? '' : String(Number(process.env.PS_PORT)-8080));
test.beforeAll(() => { __x(`docker exec -i ${__P}-db-1 mysql -padmin prestashop`, { input: "-- Activer le mode multistore pour permettre la s\u00e9lection d'une seule boutique\nUPDATE ps_configuration SET value = 1 WHERE name = 'PS_MULTISHOP_ACTIVE';" }); });
// Issue: Uploading logo in Design -> Theme & Logo in multistore with a single shop selected causes TypeError with PHP 8.3
// The bug occurs in LogoUploader::updateInMultiShopContext when Shop::setContext(Shop::CONTEXT_SHOP) is called without the shop ID.

const fs = require('fs');

test('le téléchargement du logo en contexte multistore (une seule boutique) ne doit pas provoquer de crash', async ({ page }) => {
  // Création d'un fichier image valide (PNG 1x1) pour le téléchargement
  const logoPath = 'logo_test.png';
  fs.writeFileSync(logoPath, Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64'));

  await page.goto('/admin-dev/');

  // Gestion de la page de sécurité Symfony si elle apparaît
  if (await page.getByText(/comprends les risques|understand the risks/i).isVisible()) {
    await page.getByText(/comprends les risques|understand the risks/i).click();
  }

  // 1. Passer en contexte "Une seule boutique"
  // Le sélecteur de boutique est un bouton dans la barre supérieure. 
  // On tente plusieurs sélecteurs robustes : la classe .shop-selector ou le texte courant "Toutes les boutiques"
  const shopSelector = page.locator('.shop-selector, button:has-text(/Toutes les boutiques|All shops/i)').first();
  await shopSelector.click();
  
  // Sélectionner le mode "Utiliser une seule boutique" (radio button)
  await page.locator('input[value="shop"]').click();
  
  // Cocher la première boutique disponible
  await page.locator('input[type="checkbox"]').first().click();
  
  // Valider la sélection
  await page.locator('button.btn-primary').click();

  // 2. Naviguer vers Design -> Theme & Logo
  // On cherche le lien vers le contrôleur AdminThemesLogo
  await page.click('a[href*="controller=AdminThemesLogo"]');

  // 3. Télécharger le logo
  // On cible l'input de fichier pour le logo d'en-tête
  await page.setInputFiles('input[name="logo"]', logoPath);

  // 4. Sauvegarder et vérifier que la réponse n'est pas une erreur 500 (TypeError PHP)
  const responsePromise = page.waitForResponse(r => 
    r.url().includes('controller=AdminThemesLogo') && r.request().method() === 'POST'
  );
  
  await page.click('button[type="submit"]');
  const response = await responsePromise;

  // En mode production, un TypeError PHP 8.3 se traduit par un statut HTTP 500.
  // Le test échoue si le serveur crash (500) et passe si la requête réussit (200 ou 302).
  expect(response.status()).not.toBe(500);
});
