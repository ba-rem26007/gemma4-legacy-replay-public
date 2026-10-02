-- #41652 : deux commandes (941652, 941653, clones de la commande démo 1, état « Paiement accepté »)
-- contenant chacune une ligne du produit 1 avec une déclinaison 941652 qui N'EXISTE PLUS.
SET SESSION sql_mode = '';
UPDATE ps_configuration SET value = '0' WHERE name = 'PS_MULTISHOP_FEATURE_ACTIVE';
-- pas d'envoi d'e-mail (sendmail absent : l'échec d'envoi afficherait une erreur générique)
UPDATE ps_configuration SET value = '3' WHERE name = 'PS_MAIL_METHOD';
DELETE FROM ps_stock_mvt WHERE id_order IN (941652, 941653);
DELETE FROM ps_stock_available WHERE id_product = 1 AND id_product_attribute = 941652;
DELETE FROM ps_product_attribute WHERE id_product_attribute = 941652;
DELETE FROM ps_order_history WHERE id_order IN (941652, 941653);
DELETE FROM ps_order_carrier WHERE id_order IN (941652, 941653);
DELETE FROM ps_order_detail WHERE id_order IN (941652, 941653);
DELETE FROM ps_orders WHERE id_order IN (941652, 941653);

CREATE TEMPORARY TABLE t_o AS SELECT * FROM ps_orders WHERE id_order = 1;
UPDATE t_o SET id_order = 941652, reference = 'ORA41652A', current_state = 2, valid = 1;
INSERT INTO ps_orders SELECT * FROM t_o;
UPDATE t_o SET id_order = 941653, reference = 'ORA41652B';
INSERT INTO ps_orders SELECT * FROM t_o;

CREATE TEMPORARY TABLE t_d AS SELECT * FROM ps_order_detail WHERE id_order_detail = 1;
UPDATE t_d SET id_order_detail = 941652, id_order = 941652, product_id = 1, product_attribute_id = 941652, product_quantity = 1;
INSERT INTO ps_order_detail SELECT * FROM t_d;
UPDATE t_d SET id_order_detail = 941653, id_order = 941653;
INSERT INTO ps_order_detail SELECT * FROM t_d;

CREATE TEMPORARY TABLE t_c AS SELECT * FROM ps_order_carrier WHERE id_order = 1;
UPDATE t_c SET id_order_carrier = 941652, id_order = 941652;
INSERT INTO ps_order_carrier SELECT * FROM t_c;
UPDATE t_c SET id_order_carrier = 941653, id_order = 941653;
INSERT INTO ps_order_carrier SELECT * FROM t_c;

INSERT INTO ps_order_history (id_employee, id_order, id_order_state, date_add)
  VALUES (0, 941652, 2, NOW()), (0, 941653, 2, NOW());
