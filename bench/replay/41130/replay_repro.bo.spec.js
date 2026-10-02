// Test de reproduction écrit par Gemma (gemma-4-31b-it) à partir du TICKET SEUL — PR #41130
const { test, expect } = require('@playwright/test');
const { execSync: __x } = require('child_process');
const __P = 'psbench' + ((process.env.PS_PORT||'8081')==='8081' ? '' : String(Number(process.env.PS_PORT)-8080));
test.beforeAll(() => { __x(`docker exec -i ${__P}-db-1 mysql -padmin prestashop`, { input: "UPDATE ps_configuration SET value = '1' WHERE name = 'PS_MULTISHOP_LITE';\nUPDATE ps_configuration SET value = '1' WHERE name = 'PS_MULTISHOP_FULL';\n\n-- Cr\u00e9ation de la table OAuth2 si elle est absente de l'environnement de d\u00e9mo\nCREATE TABLE IF NOT EXISTS ps_oauth_access_tokens (\n    access_token VARCHAR(255) NOT NULL,\n    expires_at DATETIME NOT NULL,\n    client_id INT NOT NULL,\n    employee_id INT NOT NULL,\n    scopes TEXT,\n    PRIMARY KEY (access_token)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n-- Injection d'un token valide pour contourner l'authentification et atteindre le handler\nDELETE FROM ps_oauth_access_tokens WHERE access_token = 'repro_token';\nINSERT INTO ps_oauth_access_tokens (access_token, expires_at, client_id, employee_id, scopes) \nVALUES ('repro_token', '2030-01-01 00:00:00', 1, 1, 'zone_write');" }); });
// Issue: Admin API write operation on entities using associateWithShops (e.g. Zones)
// throws a 500 error when multistore is enabled because Context::getContext()->employee is null.


test('Admin API: création d\'une zone en mode multistore ne doit pas provoquer d\'erreur 500', async ({ page }) => {
  // On utilise le token injecté via setup.sql. 
  // L'authentification OAuth2 ne peuple pas l'objet employee du contexte legacy, 
  // ce qui provoque l'erreur "Call to a member function hasAuthOnShop() on null" 
  // lors de l'appel à associateWithShops().
  const response = await page.request.post('/admin-api/zones?shopId=1', {
    data: {
      name: 'Zone de Test Reproduction',
    },
    headers: {
      'Authorization': 'Bearer repro_token',
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
  });

  // Le bug produit une erreur 500 fatale.
  expect(response.status(), 'La requête API ne doit pas renvoyer une erreur 500').not.toBe(500);
  
  // Vérification du comportement attendu : création réussie (201 Created)
  expect(response.status(), 'La zone doit être créée avec succès').toBe(201);
  
  const body = await response.json();
  expect(body, 'La réponse doit contenir l\'ID de la zone créée').toHaveProperty('zoneId');
});
