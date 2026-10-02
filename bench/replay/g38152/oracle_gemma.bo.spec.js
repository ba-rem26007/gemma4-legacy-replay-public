// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #38152, validé pre/post automatiquement
// Issue: Blue AJAX spinner never ends on legacy pages.
// Le spinner #ajax_running reste visible sur certaines pages legacy (ex: Groupes de clients)
// car il n'est pas explicitement masqué au chargement si aucune requête AJAX n'est active.
const { test, expect } = require('@playwright/test');

test('le spinner AJAX est masqué au chargement des pages legacy', async ({ page }) => {
  // Accès au back-office
  await page.goto('/admin-dev/');

  // Gestion de la page de sécurité Symfony si elle apparaît
  const risks = page.locator('text=/comprends les risques|understand the risks/i');
  if (await risks.isVisible()) {
    await risks.click();
  }

  // Navigation vers une page legacy citée dans le ticket (Groupes de clients)
  // On utilise 'networkidle' pour s'assurer que les scripts initiaux ont été exécutés.
  await page.goto('/admin-dev/index.php?controller=Group', { waitUntil: 'networkidle' });

  // Le bug est que l'élément #ajax_running est présent dans le DOM et visible (display != none)
  // par défaut sur ces pages, et le JS ne le masque pas si $.active === 0.
  // On vérifie la valeur exacte de la propriété CSS 'display'.
  const displayStyle = await page.locator('#ajax_running').evaluate((el) => {
    return window.getComputedStyle(el).display;
  });

  // Avant le correctif, displayStyle sera 'block' ou 'flex' (donc != 'none').
  // Après le correctif, il doit être 'none'.
  expect(displayStyle, 'Le spinner AJAX #ajax_running doit avoir un style display: none au chargement').toBe('none');
});
