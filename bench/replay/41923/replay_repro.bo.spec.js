// Test de reproduction écrit par Gemma (gemma-4-31b-it) à partir du TICKET SEUL — PR #41923
const { test, expect } = require('@playwright/test');
const { execSync: __x } = require('child_process');
const __P = 'psbench' + ((process.env.PS_PORT||'8081')==='8081' ? '' : String(Number(process.env.PS_PORT)-8080));
test.beforeAll(() => { __x(`docker exec -i ${__P}-db-1 mysql -padmin prestashop`, { input: "-- Activer le multistore\nUPDATE ps_configuration SET value = '1' WHERE name = 'PS_MULTISHOP_ACTIVE';\n-- Activer le stock partag\u00e9 (PS_SHOP_STOCK_MANAGEMENT = 0 signifie stock partag\u00e9)\nUPDATE ps_configuration SET value = '0' WHERE name = 'PS_SHOP_STOCK_MANAGEMENT';\n-- S'assurer que le produit 1 a des d\u00e9clinaisons et que leur comportement est \"Autoriser les commandes\" (1)\nUPDATE ps_stock_available SET out_of_stock = 1 WHERE id_product = 1;" }); });
// Issue #... : Le changement du comportement de stock en mode stock partagé 
// ne s'applique qu'au produit de base et non aux déclinaisons.

const { execSync } = require('child_process');

const PROJ = 'psbench' + ((process.env.PS_PORT || '8081') === '8081' ? '' : String(Number(process.env.PS_PORT) - 8080));
const sql = q => execSync(`docker exec ${PROJ}-db-1 mysql -padmin prestashop -N -e "${q}"`).toString();

test('le changement de comportement de stock doit s\'appliquer à toutes les déclinaisons en stock partagé', async ({ page }) => {
  // Utilisation de l'URL legacy qui redirige vers la page Symfony avec le token correct
  await page.goto('/admin-dev/index.php?controller=AdminProducts&id_product=1&updateproduct');
  
  if (page.url().includes('security/compromised')) {
    await page.locator('a:has-text("Oui"), a:has-text("understand the risks")').first().click();
  }

  // Attendre que la page de modification du produit soit chargée (URL Symfony)
  await expect(page).toHaveURL(/sell\/catalog\/product\/\d+\/edit/);

  // Navigation vers l'onglet Quantités
  await page.getByRole('link', { name: /Quantités/i }).click();
  await page.waitForLoadState();

  // Modifier le comportement : "Refuser les commandes" (valeur '0')
  const stockBehaviorSelect = page.locator('select[name="out_of_stock"]');
  await expect(stockBehaviorSelect).toBeVisible();
  await stockBehaviorSelect.selectOption('0');

  // Sauvegarder via le bouton de soumission
  await page.locator('button[type="submit"]').click();
  await page.waitForLoadState();

  // Vérification en base de données : 
  // On compte combien de lignes pour le produit 1 n'ont PAS la valeur 0.
  // Si le bug est présent, les déclinaisons (id_product_attribute > 0) resteront à 1.
  const countNonZero = sql("SELECT COUNT(*) FROM ps_stock_available WHERE id_product = 1 AND out_of_stock != 0");
  
  expect(countNonZero.trim(), 'Toutes les lignes de ps_stock_available pour le produit 1 doivent être passées à 0').toBe('0');
});
