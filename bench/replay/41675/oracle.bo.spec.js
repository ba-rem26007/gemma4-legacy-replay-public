// Issue #41675 : le HTMLPurifier du filtre Twig « raw_purified » doit écrire son cache de
// définitions dans le dossier de cache de PrestaShop (var/cache/<env>/purifier) et NON dans
// vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer.
// Le test vide le cache vendor, affiche une page BO utilisant raw_purified (fiche client),
// puis inspecte le système de fichiers du conteneur.
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');

const PORT = process.env.PS_PORT || '8081';
const N = Number(PORT) - 8080;
const PROJ = `psbench${N === 1 ? '' : N}`;
const sh = cmd => execSync(`docker exec -w /var/www/html ${PROJ}-ps-1 sh -c '${cmd}'`, { stdio: ['ignore', 'pipe', 'ignore'] }).toString().trim();
const VENDOR = 'vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer';

test('cache HTMLPurifier (twig) dans var/cache', async ({ page }) => {
  sh(`find ${VENDOR} -name "*.ser" -delete; rm -rf var/cache/*/purifier`);
  await page.goto('/admin-dev/');
  const href = await page.locator('a[href*="sell/customers"]').first().getAttribute('href');
  const tok = href.match(/_token=([^&#]+)/)[1];
  // fiche du client démo n°2 : le bloc Commandes utilise raw_purified (« pour un montant total de … »)
  await page.goto(`/admin-dev/sell/customers/2/view?_token=${tok}`);
  await expect(page.getByText(/pour un montant total de/i).first()).toBeVisible();

  const inVendor = sh(`find ${VENDOR} -name "*.ser" | wc -l`);
  const inCache = sh('find var/cache -path "*purifier*" -name "*.ser" | wc -l');
  expect(inVendor, 'fichiers de cache écrits dans vendor/').toBe('0');
  expect(Number(inCache), 'fichiers de cache dans var/cache/*/purifier').toBeGreaterThan(0);
});
