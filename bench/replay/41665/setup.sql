-- #41665 : commande 941665 passée en anglais (id_lang=2) avec une facture n° 941665 ;
-- employé en français (id_lang=1). Préfixes : FR « #FA », EN « #IN ».
SET SESSION sql_mode = '';
UPDATE ps_employee SET id_lang = 1 WHERE email = 'demo@prestashop.com';
UPDATE ps_configuration_lang SET value = '#FA' WHERE id_lang = 1 AND id_configuration = (SELECT id_configuration FROM ps_configuration WHERE name = 'PS_INVOICE_PREFIX' AND id_shop IS NULL AND id_shop_group IS NULL LIMIT 1);
UPDATE ps_configuration_lang SET value = '#IN' WHERE id_lang = 2 AND id_configuration = (SELECT id_configuration FROM ps_configuration WHERE name = 'PS_INVOICE_PREFIX' AND id_shop IS NULL AND id_shop_group IS NULL LIMIT 1);
DELETE FROM ps_order_invoice WHERE id_order = 941665;
DELETE FROM ps_order_history WHERE id_order = 941665;
DELETE FROM ps_order_carrier WHERE id_order = 941665;
DELETE FROM ps_order_detail WHERE id_order = 941665;
DELETE FROM ps_orders WHERE id_order = 941665;

CREATE TEMPORARY TABLE t_o AS SELECT * FROM ps_orders WHERE id_order = 1;
UPDATE t_o SET id_order = 941665, reference = 'ORA41665', current_state = 2, valid = 1, id_lang = 2, invoice_number = 941665, invoice_date = NOW();
INSERT INTO ps_orders SELECT * FROM t_o;
CREATE TEMPORARY TABLE t_d AS SELECT * FROM ps_order_detail WHERE id_order_detail = 1;
UPDATE t_d SET id_order_detail = 941665, id_order = 941665, id_order_invoice = 941665;
INSERT INTO ps_order_detail SELECT * FROM t_d;
CREATE TEMPORARY TABLE t_c AS SELECT * FROM ps_order_carrier WHERE id_order = 1;
UPDATE t_c SET id_order_carrier = 941665, id_order = 941665, id_order_invoice = 941665;
INSERT INTO ps_order_carrier SELECT * FROM t_c;
INSERT INTO ps_order_history (id_employee, id_order, id_order_state, date_add) VALUES (0, 941665, 2, NOW());
INSERT INTO ps_order_invoice (id_order_invoice, id_order, number, delivery_number, total_paid_tax_excl, total_paid_tax_incl,
  total_products, total_products_wt, shipping_tax_computation_method, date_add)
  SELECT 941665, 941665, 941665, 0, total_paid_tax_excl, total_paid_tax_incl, total_products, total_products_wt, 0, NOW()
  FROM ps_orders WHERE id_order = 1;
