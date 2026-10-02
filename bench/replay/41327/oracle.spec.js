// Issue #41327 : si l'insertion d'une ligne de commande (OrderDetail) échoue pendant la validation de
// la commande, la commande ne doit pas être finalisée silencieusement avec des articles en moins
// (le correctif lève une exception). setup.sql installe un trigger MySQL qui fait échouer l'insertion de
// la ligne du produit 8 commandé en quantité 7 (marqueur). Le client pub@prestashop.com commande le
// produit 1 et 7 x le produit 8 par virement :
// on ne doit PAS aboutir à une confirmation de commande qui ne contiendrait pas les deux articles.
const { test, expect } = require('@playwright/test');

test('pas de commande confirmée avec des lignes manquantes', async ({ page }) => {
  test.setTimeout(90_000);
  // Connexion client
  await page.goto('/index.php?controller=authentication');
  await page.locator('input[name="email"]').first().fill('pub@prestashop.com');
  await page.locator('input[name="password"]').first().fill('123456789');
  await Promise.all([page.waitForNavigation(), page.locator('#login-form button[type="submit"], button[data-link-action="sign-in"]').first().click()]);

  // Panier : produit 1 (x1) et produit 8 (x7, quantité marqueur ciblée par le trigger)
  for (const [p, qty] of [['id_product=1&id_product_attribute=3', '1'], ['id_product=8', '7']]) {
    await page.goto(`/index.php?${p}&controller=product`);
    await page.locator('#quantity_wanted').fill(qty);
    const added = page.waitForResponse(r => r.url().includes('cart') && r.request().method() === 'POST');
    await page.locator('#add-to-cart-or-refresh [data-button-action="add-to-cart"]').click();
    await added;
  }

  // Tunnel de commande
  await page.goto('/index.php?controller=order');
  const next = async (sel) => { const b = page.locator(sel).first(); if (await b.isVisible().catch(() => false)) await Promise.all([page.waitForLoadState('load'), b.click()]); };
  await next('button[name="confirm-addresses"]');
  await next('button[name="confirmDeliveryOption"]');
  await page.locator('input[name="payment-option"][data-module-name="ps_wirepayment"]').check({ force: true });
  await page.locator('input[name^="conditions_to_approve"]').check({ force: true });
  await Promise.all([page.waitForLoadState('load'), page.locator('#payment-confirmation button[type="submit"], .js-payment-confirmation button[type="submit"]').first().click()]);
  await page.waitForLoadState('networkidle');

  // Si une confirmation de commande est affichée, elle doit contenir les deux articles commandés
  // (avant correctif : commande validée avec la seule ligne du produit 1)
  const confirmed = /[?&]id_order=\d+/.test(page.url()) || (await page.locator('body#order-confirmation').count()) > 0;
  if (confirmed) {
    await expect(page.locator('.order-confirmation__product, #order-items .order-line')).toHaveCount(2);
  } else {
    // Correctif : la validation échoue (exception) au lieu de créer une commande incomplète
    expect(page.url()).toContain('ps_wirepayment/validation');
  }
});
