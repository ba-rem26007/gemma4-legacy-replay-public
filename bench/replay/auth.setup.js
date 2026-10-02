// Connexion BO partagée : sauvegarde la session dans .auth/bo.json
const { test: setup } = require('@playwright/test');
setup('connexion BO', async ({ page }) => {
  await page.goto('/admin-dev/index.php?controller=AdminLogin');
  await page.fill('#email', 'demo@prestashop.com');
  await page.fill('#passwd', 'prestashop_demo');
  await page.click('#submit_login');
  await page.waitForURL(/AdminDashboard/);
  await page.context().storageState({ path: `.auth/bo-${process.env.PS_PORT || 8081}.json` });
});
