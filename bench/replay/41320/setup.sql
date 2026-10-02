-- #41320 : la commande 2 (en attente de chèque) reçoit une 3e ligne dont le produit (id 99032)
-- n'existe plus dans le catalogue (produit supprimé). Idempotent : la ligne est recréée à chaque passage.
SET SESSION sql_mode = '';
UPDATE ps_configuration SET value = '0' WHERE name = 'PS_MULTISHOP_FEATURE_ACTIVE';
DELETE FROM ps_order_detail_tax WHERE id_order_detail = 99032;
DELETE FROM ps_order_detail WHERE id_order_detail = 99032 OR (id_order = 2 AND product_id = 99032);
DELETE FROM ps_cart_product WHERE id_cart = 2 AND id_product = 99032;
DELETE FROM ps_product WHERE id_product = 99032;
DELETE FROM ps_product_shop WHERE id_product = 99032;
DELETE FROM ps_product_lang WHERE id_product = 99032;
CREATE TEMPORARY TABLE tmp_od41320 SELECT * FROM ps_order_detail WHERE id_order_detail = 4;
UPDATE tmp_od41320 SET id_order_detail = 99032, product_id = 99032, product_attribute_id = 0,
  product_name = 'Produit supprime bench41320', product_reference = 'BENCH41320';
INSERT INTO ps_order_detail SELECT * FROM tmp_od41320;
DROP TEMPORARY TABLE tmp_od41320;
INSERT INTO ps_cart_product (id_cart, id_product, id_address_delivery, id_shop, id_product_attribute, id_customization, quantity, date_add)
  SELECT id_cart, 99032, id_address_delivery, 1, 0, 0, 1, NOW() FROM ps_orders WHERE id_order = 2;
