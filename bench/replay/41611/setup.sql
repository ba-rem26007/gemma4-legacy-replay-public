-- Trois règles panier aux noms distincts, sans restriction de compatibilité
DELETE FROM ps_cart_rule_lang WHERE id_cart_rule IN (41611, 41612, 41613);
DELETE FROM ps_cart_rule_shop WHERE id_cart_rule IN (41611, 41612, 41613);
DELETE FROM ps_cart_rule_combination WHERE id_cart_rule_1 IN (41611, 41612, 41613) OR id_cart_rule_2 IN (41611, 41612, 41613);
DELETE FROM ps_cart_rule WHERE id_cart_rule IN (41611, 41612, 41613);
INSERT INTO ps_cart_rule (id_cart_rule, id_customer, date_from, date_to, description, quantity, quantity_per_user, priority, partial_use,
  code, minimum_amount, minimum_amount_tax, minimum_amount_currency, minimum_amount_shipping, country_restriction, carrier_restriction,
  group_restriction, cart_rule_restriction, product_restriction, shop_restriction, free_shipping, reduction_percent, reduction_amount,
  reduction_tax, reduction_currency, reduction_product, reduction_exclude_special, gift_product, gift_product_attribute, highlight, active, date_add, date_upd)
VALUES
 (41611, 0, '2020-01-01 00:00:00', '2099-01-01 00:00:00', '', 100, 1, 1, 0, 'ORA10', 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 10, 0, 0, 1, 0, 0, 0, 0, 0, 1, NOW(), NOW()),
 (41612, 0, '2020-01-01 00:00:00', '2099-01-01 00:00:00', '', 100, 1, 1, 0, 'ORA50', 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 50, 0, 0, 1, 0, 0, 0, 0, 0, 1, NOW(), NOW()),
 (41613, 0, '2020-01-01 00:00:00', '2099-01-01 00:00:00', '', 100, 1, 1, 0, 'ORAFS', 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 1, 0, 0, 0, 0, 0, 1, NOW(), NOW());
INSERT INTO ps_cart_rule_lang (id_cart_rule, id_lang, name)
SELECT r.id, l.id_lang, r.name FROM ps_lang l
JOIN (SELECT 41611 AS id, '10% off' AS name UNION ALL SELECT 41612, '-50% summer' UNION ALL SELECT 41613, 'Free shipping') r;
