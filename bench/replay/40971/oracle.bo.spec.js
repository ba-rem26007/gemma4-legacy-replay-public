// Issue #40971 : LogoUploader::updateInMultiShopContext() appelait Shop::setContext(CONTEXT_GROUP) sans
// id de groupe : la valeur « groupe » du logo était lue sur le groupe 0 (donc la valeur globale), ce qui
// faussait la comparaison et supprimait le mauvais fichier. Scénario (multiboutique, setup.sql) :
//  1. contexte GROUPE « Default » : on téléverse un logo d'en-tête (logo du groupe) ;
//  2. contexte BOUTIQUE 1 (qui hérite du logo du groupe) : on téléverse un autre logo ;
//  3. le fichier du logo du groupe, toujours utilisé par le groupe, ne doit PAS avoir été supprimé.
// NB : le multiboutique et la boutique 2 restent actifs après le test (non désactivables tant que 2 boutiques existent).
const zlib = require('zlib');
const { test, expect } = require('@playwright/test');

function png(w, h, rgb) { // petite image PNG unie
  const t = []; for (let n = 0; n < 256; n++) { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1; t[n] = c >>> 0; }
  const crc = b => { let c = 0xffffffff; for (const x of b) c = t[(c ^ x) & 255] ^ (c >>> 8); return (c ^ 0xffffffff) >>> 0; };
  const chunk = (ty, d) => { const l = Buffer.alloc(4); l.writeUInt32BE(d.length); const td = Buffer.concat([Buffer.from(ty), d]); const c = Buffer.alloc(4); c.writeUInt32BE(crc(td)); return Buffer.concat([l, td, c]); };
  const ih = Buffer.alloc(13); ih.writeUInt32BE(w, 0); ih.writeUInt32BE(h, 4); ih[8] = 8; ih[9] = 2;
  const raw = Buffer.alloc((w * 3 + 1) * h);
  for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) raw.set(rgb, y * (w * 3 + 1) + 1 + x * 3);
  return Buffer.concat([Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]), chunk('IHDR', ih), chunk('IDAT', zlib.deflateSync(raw)), chunk('IEND', Buffer.alloc(0))]);
}

async function uploadHeaderLogo(page, themesUrl, shopContext, name, rgb) {
  await page.goto(`${themesUrl}&setShopContext=${shopContext}`);
  const restrict = page.locator('#form_header_logo_is_restricted_to_shop');
  if (await restrict.count() && !(await restrict.isChecked())) await restrict.check({ force: true });
  await page.locator('#form_header_logo').setInputFiles({ name, mimeType: 'image/png', buffer: png(60, 30, rgb) });
  const form = page.locator('#form_header_logo').locator('xpath=ancestor::form');
  await Promise.all([page.waitForNavigation(), form.locator('button.btn-primary').first().click()]);
  await expect(page.locator('.alert-success:visible').first()).toBeVisible();
  return page.locator('img.header-logo').getAttribute('src');
}

test('le logo du groupe n’est pas supprimé lors d’un envoi en contexte boutique', async ({ page }) => {
  await page.goto('/admin-dev/');
  const themesUrl = await page.locator('a[href*="improve/design/themes/"]').first().getAttribute('href');

  const groupLogo = await uploadHeaderLogo(page, themesUrl, 'g-1', 'group.png', [30, 30, 200]);
  expect(groupLogo).toMatch(/\/img\/logo-\d+\.jpg$/);
  expect((await page.request.get(groupLogo)).status()).toBe(200);

  await page.waitForTimeout(1500); // le nom du fichier logo dépend de time()
  const shopLogo = await uploadHeaderLogo(page, themesUrl, 's-1', 'shop.png', [30, 200, 30]);
  expect(shopLogo).not.toBe(groupLogo);

  // Le fichier du logo du groupe doit toujours exister
  expect((await page.request.get(groupLogo)).status()).toBe(200);
});
