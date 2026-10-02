// Issue #41394 : [Multiboutique] erreur (TypeError dans RouteValidator::isRouteValid) à
// l'enregistrement du bloc « Schéma des URL » (Trafic & SEO) dans le contexte d'une seule boutique.
// setup.sql active le multiboutique avec une 2e boutique. Le test se place en contexte
// boutique 1, surcharge la règle « catégorie » et enregistre : la page doit afficher la
// confirmation de mise à jour (pas d'erreur 500) et la valeur doit être conservée.
const { test, expect } = require('@playwright/test');

test('enregistrer le schéma des URL en contexte boutique unique', async ({ page }) => {
  await page.goto('/admin-dev/index.php?controller=AdminMeta&setShopContext=s-1');
  if (page.url().includes('security/compromised')) await page.locator('a:has-text("Oui")').first().click();
  const form = page.locator('form[name="meta_settings_url_schema_form"]');
  await expect(form).toBeVisible();
  await form.locator('input[name="meta_settings_url_schema_form[multistore_category_rule]"]').check();
  const rule = form.locator('input[name="meta_settings_url_schema_form[category_rule]"]');
  await expect(rule).toBeEnabled();
  await rule.fill('{id}-{rewrite}');
  const [res] = await Promise.all([
    page.waitForResponse(r => r.url().includes('seo-urls/url-schema') && r.request().method() === 'POST'),
    form.locator('button[type="submit"], button.btn-primary').last().click(),
  ]);
  expect(res.status(), 'réponse de l’enregistrement').toBeLessThan(400);
  await page.waitForLoadState();
  await expect(page.locator('.alert-success', { hasText: 'Mise à jour réussie' })).toBeVisible();
  await expect(page.locator('form[name="meta_settings_url_schema_form"] input[name="meta_settings_url_schema_form[category_rule]"]')).toHaveValue('{id}-{rewrite}');
});
