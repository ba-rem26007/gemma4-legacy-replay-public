// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37942, validé pre/post automatiquement
// Issue # (Category miniature images are not present in fixtures)
// Le bug est que les fichiers de miniatures (_thumb.jpg) ne sont pas générés lors de l'install.
// Si le correctif est absent, soit l'image pointe vers le fichier _thumb.jpg mais renvoie un 404,
// soit elle pointe vers l'image principale (sans _thumb), ce qui est un comportement incorrect pour une miniature.
const { test, expect } = require('@playwright/test');

test('les miniatures des sous-catégories utilisent le fichier _thumb et sont accessibles', async ({ page }) => {
  // Accès à la catégorie Vêtements (id=3) qui contient des sous-catégories
  await page.goto('/index.php?controller=category&id_category=3');

  // On cible l'image d'une sous-catégorie (Hommes ou Femmes)
  const subCatImg = page.getByAltText(/Hommes|Femmes/i).first();
  
  await expect(subCatImg).toBeVisible();
  
  const src = await subCatImg.getAttribute('src');
  expect(src, 'L\'attribut src de l\'image doit être présent').not.toBeNull();

  // ASSERTION CRUCIALE : La miniature DOIT pointer vers un fichier se terminant par _thumb.jpg
  // Avant le correctif, soit le fichier n'existe pas (404), soit le système utilise l'image source.
  expect(src, `L'image doit être une miniature (_thumb.jpg), mais on a trouvé : ${src}`).toContain('_thumb.jpg');

  // On vérifie que le fichier existe réellement sur le disque (statut 200)
  const response = await page.request.get(src);
  expect(response.status(), `Le fichier miniature (${src}) doit renvoyer un statut 200 OK`).toBe(200);
});
