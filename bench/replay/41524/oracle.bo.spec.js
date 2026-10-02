// Issue #41524 (bug #20346) : le moyen de paiement n'est pas imprimé sur la facture si la
// commande n'est pas encore payée (aucun paiement enregistré).
// setup.sql crée une facture pour la commande de démo n° 4 (« Payment by check », sans paiement).
// Le test télécharge le PDF de la facture et vérifie que la zone « Moyen de paiement »
// contient « Payment by check » (texte extrait des flux du PDF, police Helvetica non subsettée).
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const zlib = require('zlib');

function pdfText(buf) {
  const bin = buf.toString('latin1');
  let out = '';
  const re = /stream\r?\n/g;
  let m;
  while ((m = re.exec(bin))) {
    const start = m.index + m[0].length;
    const end = bin.indexOf('endstream', start);
    if (end < 0) break;
    const raw = Buffer.from(bin.slice(start, end), 'latin1');
    try { out += zlib.inflateSync(raw).toString('latin1'); } catch { out += raw.toString('latin1'); }
    re.lastIndex = end;
  }
  // chaînes littérales des opérateurs Tj / TJ
  return (out.match(/\((?:\\.|[^\\)])*\)/g) || []).map(s => s.slice(1, -1).replace(/\\(.)/g, '$1')).join('');
}

test('moyen de paiement imprimé sur la facture d’une commande non payée', async ({ page }) => {
  await page.goto('/admin-dev/index.php?controller=AdminPdf&submitAction=generateInvoicePDF&id_order_invoice=41524');
  await expect(page).toHaveURL(/security\/compromised/);
  const dl = page.waitForEvent('download');
  await page.locator('a:has-text("Oui")').first().click();
  const download = await dl;
  const text = pdfText(fs.readFileSync(await download.path()));
  expect(text, 'facture PDF lisible').toMatch(/Payment Method|Moyen de paiement|paiement/i);
  expect(text).toContain('Payment by check');
});
