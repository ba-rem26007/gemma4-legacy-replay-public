// Issue #41412 : envoi d'e-mails avec une adresse sur un domaine IDN (info@sjöbüren.se). L'e-mail de test
// (Paramètres avancés > E-mail, méthode SMTP) envoyé à cette adresse ne doit plus planter sur une adresse
// jugée non conforme à la RFC 2822 (avant : adresses passées dans htmlentities → « sj&ouml;... » → 500).
// Aucun serveur SMTP n'écoute sur 127.0.0.1:1 : une erreur de connexion est attendue, pas une erreur d'adresse.
const { test, expect } = require('@playwright/test');

test('e-mail de test vers une adresse sur domaine IDN', async ({ page }) => {
  await page.goto('/admin-dev/');
  await page.goto(await page.locator('a[href*="configure/advanced/emails/"]').first().getAttribute('href'));

  await page.locator('#form_mail_method_1').check({ force: true }); // SMTP
  await page.fill('#form_smtp_config_server', '127.0.0.1');
  await page.fill('#form_smtp_config_port', '1');
  await page.selectOption('#form_smtp_config_encryption', 'off');
  await page.fill('#test_email_sending_send_email_to', 'info@sjöbüren.se');

  const resp = page.waitForResponse(r => r.url().includes('/send-testing-email'));
  await page.locator('.js-send-test-email-btn').click();
  const r = await resp;
  // Avant correctif : 500 (RfcComplianceException « info@sj&ouml;b&uuml;ren.se does not comply with addr-spec of RFC 2822 »)
  expect(r.status()).toBe(200);
  const errors = ((await r.json()).errors || []).join(' | ');
  expect(errors).not.toMatch(/RFC ?2822|does not comply|&ouml;|&uuml;/i);
});
