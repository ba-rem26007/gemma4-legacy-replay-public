// Issue #41652 : mono-boutique — passer une commande contenant une déclinaison supprimée
// à un état « expédié » ne doit pas échouer. Avant le correctif, la 1re commande créait une ligne
// stock_available (produit, décl., id_shop=0, id_shop_group=0) et la 2e échouait avec
// « Duplicate entry ... for key product_sqlstock ».
// Données : setup.sql (commandes 941652 et 941653, ligne produit 1 / déclinaison 941652 inexistante).
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');

const PORT = process.env.PS_PORT || '8081';
const N = Number(PORT) - 8080;
const PROJ = `psbench${N === 1 ? '' : N}`;
const sql = q => execSync(`docker exec -i ${PROJ}-db-1 mysql -padmin -N prestashop`, { input: q, stdio: ['pipe', 'pipe', 'ignore'] }).toString().trim();

test.afterAll(() => sql("UPDATE ps_configuration SET value='1' WHERE name='PS_MAIL_METHOD';"));

test('changement d’état de deux commandes avec déclinaison supprimée', async ({ page }) => {
  await page.goto('/admin-dev/');
  const href = await page.locator('a[href*="sell/orders"]').first().getAttribute('href');
  const tok = href.match(/_token=([^&#]+)/)[1];

  for (const id of [941652, 941653]) {
    await page.goto(`/admin-dev/sell/orders/${id}/view?_token=${tok}`);
    const sel = page.locator('#update_order_status_action_input');
    await expect(sel).toBeAttached();
    await sel.selectOption('4', { force: true }); // « Expédié » (shipped = 1)
    await Promise.all([
      page.waitForURL(u => !u.pathname.endsWith('/status'), { timeout: 30000 }).catch(() => {}),
      page.waitForLoadState('load'),
      page.locator('#update_order_status_action_btn').click(),
    ]);
    await page.waitForLoadState('load');
    const errors = await page.locator('.alert-danger, .alert.alert-danger').allInnerTexts();
    expect(errors.join(' | '), `commande ${id} : erreur affichée`).not.toMatch(/Duplicate|SQLSTATE|erreur|error/i);
    expect(sql(`SELECT current_state FROM ps_orders WHERE id_order=${id}`), `état de la commande ${id}`).toBe('4');
  }
  // aucune ligne de stock ne doit être écrite avec id_shop = 0 en mono-boutique
  expect(sql('SELECT COUNT(*) FROM ps_stock_available WHERE id_product=1 AND id_product_attribute=941652 AND id_shop=0')).toBe('0');
});
