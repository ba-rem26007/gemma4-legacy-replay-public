// Issue #41100 : International > Traductions > Traductions des e-mails (corps) : l'aperçu HTML des
// e-mails de MODULES (modules/<module>/mails/<lang>/*.html) doit être rendu (il était vide ; seuls les
// e-mails du cœur s'affichaient). On ouvre la page via le formulaire, puis on appelle l'action AJAX
// emailHTML exactement comme le fait la page à l'ouverture d'un e-mail.
const { test, expect } = require('@playwright/test');

test("aperçu HTML des e-mails de modules non vide", async ({ page }) => {
  await page.goto('/admin-dev/');
  await page.goto(await page.locator('a[href*="international/translations"]').first().getAttribute('href'));
  await page.selectOption('#form_translation_type', 'mails');
  await page.selectOption('#form_email_content_type', 'body');
  await page.selectOption('#form_theme', 'classic');
  await page.selectOption('#form_language', 'fr');
  await Promise.all([page.waitForNavigation(), page.locator('.card', { has: page.locator('#form_translation_type') }).locator('.card-footer button.btn-primary').click()]);

  const moduleSrc = await page.locator('.email-html-frame[data-email-src*="/modules/ps_emailsubscription/mails/fr/"]').first().getAttribute('data-email-src');
  const coreSrc = await page.locator('.email-html-frame[data-email-src*="/mails/fr/"]:not([data-email-src*="/modules/"])').first().getAttribute('data-email-src');

  const preview = (src) => page.evaluate(async (email) => {
    const body = new URLSearchParams({ ajax: '1', controller: 'AdminTranslations', action: 'emailHTML', email, token: window.token });
    const r = await fetch('index.php', { method: 'POST', body });
    return r.text();
  }, src);

  // Contrôle : un e-mail du cœur est bien rendu
  expect(await preview(coreSrc)).toMatch(/<html/i);
  // Correctif : l'e-mail du module doit aussi être rendu
  expect(await preview(moduleSrc)).toMatch(/<html/i);
});
