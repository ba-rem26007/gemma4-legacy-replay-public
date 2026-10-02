-- #41573 : Réductions V2 (feature flag « discount ») + une réduction panier 941573 mise en avant (highlight = 1)
SET SESSION sql_mode = '';
UPDATE ps_feature_flag SET state = 1 WHERE name = 'discount';
DELETE FROM ps_cart_rule_lang WHERE id_cart_rule = 941573;
DELETE FROM ps_cart_rule_shop WHERE id_cart_rule = 941573;
DELETE FROM ps_cart_rule WHERE id_cart_rule = 941573;
INSERT INTO ps_cart_rule (id_cart_rule, id_customer, date_from, date_to, description, quantity, quantity_per_user, priority,
  partial_use, code, minimum_amount, minimum_amount_tax, minimum_amount_currency, minimum_amount_shipping,
  country_restriction, carrier_restriction, group_restriction, cart_rule_restriction, product_restriction, shop_restriction,
  free_shipping, reduction_percent, reduction_amount, reduction_tax, reduction_currency, reduction_product,
  reduction_exclude_special, gift_product, gift_product_attribute, highlight, active, date_add, date_upd,
  id_cart_rule_type, minimum_product_quantity)
VALUES (941573, 0, '2020-01-01 00:00:00', '2099-12-31 23:59:00', 'oracle', 100, 1, 1,
  1, 'ORACLE41573', 0, 0, 1, 0,
  0, 0, 0, 0, 0, 0,
  0, 10.00, 0, 0, 1, 0,
  0, 0, 0, 1, 1, NOW(), NOW(),
  2, 0);
INSERT INTO ps_cart_rule_lang (id_cart_rule, id_lang, name) VALUES (941573, 1, 'Oracle 41573'), (941573, 2, 'Oracle 41573');
