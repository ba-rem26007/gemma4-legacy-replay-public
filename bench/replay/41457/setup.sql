-- Numérotation des factures avec l'année (année après le numéro) et préfixe par défaut
DELETE FROM ps_configuration WHERE name IN ('PS_INVOICE_USE_YEAR', 'PS_INVOICE_YEAR_POS');
INSERT INTO ps_configuration (id_shop_group, id_shop, name, value, date_add, date_upd) VALUES
  (NULL, NULL, 'PS_INVOICE_USE_YEAR', '1', NOW(), NOW()),
  (NULL, NULL, 'PS_INVOICE_YEAR_POS', '0', NOW(), NOW());
-- Facture n° 7706 (datée 2026) pour la commande de démo n° 1
DELETE FROM ps_order_invoice WHERE id_order_invoice = 41457;
INSERT INTO ps_order_invoice (id_order_invoice, id_order, number, delivery_number, delivery_date,
  total_discount_tax_excl, total_discount_tax_incl, total_paid_tax_excl, total_paid_tax_incl,
  total_products, total_products_wt, total_shipping_tax_excl, total_shipping_tax_incl,
  shipping_tax_computation_method, total_wrapping_tax_excl, total_wrapping_tax_incl, shop_address, note, date_add)
SELECT 41457, id_order, 7706, 0, NULL, 0, 0, total_paid_tax_excl, total_paid_tax_incl,
  total_products, total_products_wt, total_shipping_tax_excl, total_shipping_tax_incl,
  0, 0, 0, '', '', '2026-01-15 10:00:00' FROM ps_orders WHERE id_order = 1;
UPDATE ps_order_detail SET id_order_invoice = 41457 WHERE id_order = 1;
UPDATE ps_orders SET invoice_number = 7706, invoice_date = '2026-01-15 10:00:00' WHERE id_order = 1;
