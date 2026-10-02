// Issue #41727 : un module actif dont le dossier src/Entity contient des sous-dossiers avec
// des fichiers index.php (« exit » de protection) ne doit pas casser le back-office :
// PrestaShop doit supprimer récursivement ces index.php (pas seulement src/Entity/index.php)
// avant le scan ApiPlatform, et le BO doit s'afficher normalement.
// Le test crée les fichiers du module dans le conteneur (le module est déclaré actif par setup.sql),
// vide le cache, charge le BO, puis nettoie (module retiré) en fin de test.
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');

const PORT = process.env.PS_PORT || '8081';
const N = Number(PORT) - 8080;
const PROJ = `psbench${N === 1 ? '' : N}`;
const sh = cmd => execSync(`docker exec -i -w /var/www/html ${PROJ}-ps-1 sh`, { input: cmd, stdio: ['pipe', 'pipe', 'ignore'] }).toString().trim();
const sql = q => execSync(`docker exec -i ${PROJ}-db-1 mysql -padmin -N prestashop`, { input: q, stdio: ['pipe', 'pipe', 'ignore'] }).toString().trim();
const M = 'modules/oracle41727';

const INDEX = `<?php
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Location: ../');
exit;
`;
const MODULE = `<?php
if (!defined('_PS_VERSION_')) { exit; }
class Oracle41727 extends Module
{
    public function __construct()
    {
        $this->name = 'oracle41727';
        $this->version = '1.0.0';
        $this->author = 'bench';
        parent::__construct();
        $this->displayName = 'Oracle 41727';
    }
}
`;
const ENTITY = `<?php
namespace Oracle41727\\Entity\\Sub;
class Thing
{
    public $id;
}
`;

test.beforeAll(() => {
  sh(`rm -rf ${M}; mkdir -p ${M}/src/Entity/Sub/Deeper
cat > ${M}/oracle41727.php <<'PHP'
${MODULE}PHP
cat > ${M}/src/Entity/Sub/Thing.php <<'PHP'
${ENTITY}PHP
for d in ${M} ${M}/src ${M}/src/Entity ${M}/src/Entity/Sub ${M}/src/Entity/Sub/Deeper; do
cat > $d/index.php <<'PHP'
${INDEX}PHP
done
chown -R www-data: ${M}; rm -rf var/cache/*`);
});

test.afterAll(() => {
  sql("DELETE ms FROM ps_module_shop ms JOIN ps_module m ON m.id_module = ms.id_module WHERE m.name = 'oracle41727'; DELETE FROM ps_module WHERE name = 'oracle41727';");
  sh(`rm -rf ${M} var/cache/*`);
});

test('BO accessible avec des index.php dans les sous-dossiers de src/Entity', async ({ page }) => {
  expect(sh(`ls ${M}/src/Entity/Sub/index.php`)).toContain('index.php');
  let res = null, err = '';
  try { res = await page.goto('/admin-dev/'); } catch (e) { err = String(e.message).split('\n')[0]; }
  expect(err, 'le BO doit se charger (pas de boucle de redirection / 500)').toBe('');
  expect(res.status()).toBeLessThan(500);
  await expect(page).toHaveURL(/admin-dev/);
  await expect(page.locator('#nav-sidebar, nav.nav-bar').first()).toBeVisible({ timeout: 30000 });
  // les index.php des sous-dossiers de src/Entity ont été supprimés
  expect(sh(`find ${M}/src/Entity -name index.php | wc -l`)).toBe('0');
});
