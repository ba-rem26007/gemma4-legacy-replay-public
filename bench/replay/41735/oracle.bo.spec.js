// Issue #41735 : les valeurs de caractéristique PERSONNALISÉES (saisies sur une fiche produit,
// feature_value.custom = 1) ne doivent apparaître ni dans la liste des valeurs de la
// caractéristique, ni dans le compteur « Valeurs » de la liste des caractéristiques.
// Données : setup.sql (valeur perso 941735 pour la caractéristique 1 ; 6 valeurs standard en démo).
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');

const PORT = process.env.PS_PORT || '8081';
const N = Number(PORT) - 8080;
const PROJ = `psbench${N === 1 ? '' : N}`;
const sql = q => execSync(`docker exec -i ${PROJ}-db-1 mysql -padmin -N prestashop`, { input: q, stdio: ['pipe', 'pipe', 'ignore'] }).toString().trim();

test('valeurs personnalisées exclues des listes de caractéristiques', async ({ page }) => {
  const standard = sql('SELECT COUNT(*) FROM ps_feature_value WHERE id_feature=1 AND (custom IS NULL OR custom=0)');
  await page.goto('/admin-dev/');
  const href = await page.locator('a[href*="attribute-groups"]').first().getAttribute('href');
  const tok = href.match(/_token=([^&#]+)/)[1];

  // Liste des caractéristiques : compteur de valeurs de « Composition » (id 1)
  await page.goto(`/admin-dev/sell/catalog/features/?_token=${tok}`);
  const row = page.locator('#feature_grid tbody tr').filter({ has: page.locator('td', { hasText: /^\s*1\s*$/ }) }).first();
  await expect(row.locator('td[class*="values_count"], td.column-values_count')).toHaveText(new RegExp(`^\\s*${standard}\\s*$`));

  // Liste des valeurs de la caractéristique 1
  await page.goto(`/admin-dev/sell/catalog/features/1/values?_token=${tok}`);
  const grid = page.locator('#feature_value_grid');
  await expect(grid.locator('tbody tr').first()).toBeVisible();
  await expect(grid).not.toContainText('Oracle perso 41735');
  await expect(grid.locator('tbody tr')).toHaveCount(Number(standard));
});
