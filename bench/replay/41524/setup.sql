-- Facture pour la commande de démo n° 4 (« Payment by check », en attente, sans aucun paiement enregistré)
DELETE FROM ps_order_payment WHERE order_reference = (SELECT reference FROM ps_orders WHERE id_order = 4);
DELETE FROM ps_order_invoice_payment WHERE id_order = 4;
DELETE FROM ps_order_invoice WHERE id_order_invoice = 41524;
INSERT INTO ps_order_invoice (id_order_invoice, id_order, number, delivery_number, delivery_date,
  total_discount_tax_excl, total_discount_tax_incl, total_paid_tax_excl, total_paid_tax_incl,
  total_products, total_products_wt, total_shipping_tax_excl, total_shipping_tax_incl,
  shipping_tax_computation_method, total_wrapping_tax_excl, total_wrapping_tax_incl, shop_address, note, date_add)
SELECT 41524, id_order, 41524, 0, NULL, 0, 0, total_paid_tax_excl, total_paid_tax_incl,
  total_products, total_products_wt, total_shipping_tax_excl, total_shipping_tax_incl,
  0, 0, 0, '', '', NOW() FROM ps_orders WHERE id_order = 4;
UPDATE ps_order_detail SET id_order_invoice = 41524 WHERE id_order = 4;
UPDATE ps_orders SET invoice_number = 41524, invoice_date = NOW() WHERE id_order = 4;
