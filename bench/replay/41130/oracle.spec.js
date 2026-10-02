// Issue #41130 : Admin API + multiboutique actif — une écriture sur une entité utilisant
// associateWithShops() (ici POST /admin-api/zones) ne doit pas finir en 500
// (« Call to a member function hasAuthOnShop() on null ») : attendu 201 + {"zoneId": N}
// et la zone associée aux boutiques demandées.
// Pré-requis environnement (fait ici) : l'Admin API exige HTTPS ; on déclare HTTPS=on pour
// le seul dossier admin-api via son .htaccess. Données : setup.sql (2e boutique, client API).
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');

const PORT = process.env.PS_PORT || '8081';
const N = Number(PORT) - 8080;
const PROJ = `psbench${N === 1 ? '' : N}`;
const sql = q => execSync(`docker exec -i ${PROJ}-db-1 mysql -padmin -N prestashop`, { input: q, stdio: ['pipe', 'pipe', 'ignore'] }).toString().trim();

test.beforeAll(() => {
  execSync(`docker exec ${PROJ}-ps-1 sh -c 'grep -q "^SetEnv HTTPS on" admin-api/.htaccess || echo "SetEnv HTTPS on" >> admin-api/.htaccess'`);
});
test.afterAll(() => {
  // remet la boutique en mono-boutique pour les autres tests
  sql("UPDATE ps_configuration SET value='0' WHERE name='PS_MULTISHOP_FEATURE_ACTIVE'; UPDATE ps_feature_flag SET state=0 WHERE name='admin_api_multistore';");
});

test('POST /admin-api/zones en multiboutique', async ({ request }) => {
  const tok = await request.post('/admin-api/access_token', {
    form: { grant_type: 'client_credentials', client_id: 'oracle-41130', client_secret: 'oracle41130secret', scope: 'zone_write' },
    maxRedirects: 0,
  });
  expect(tok.status(), 'obtention du jeton OAuth2').toBe(200);
  const { access_token } = await tok.json();

  const res = await request.post('/admin-api/zones?shopId=1', {
    headers: { Authorization: `Bearer ${access_token}` },
    data: { name: 'Oracle41130 zone', enabled: true, shopIds: [1, 2] },
    maxRedirects: 0,
  });
  const body = await res.text();
  expect(res.status(), body.slice(0, 300)).toBe(201);
  const zoneId = JSON.parse(body).zoneId;
  expect(zoneId).toBeGreaterThan(0);
  expect(sql(`SELECT GROUP_CONCAT(id_shop ORDER BY id_shop) FROM ps_zone_shop WHERE id_zone=${Number(zoneId)}`)).toBe('1,2');
});
