-- Déclinaison sans image propre (T-shirt colibri, taille XL / Rouge) et panier la contenant
DELETE FROM ps_product_attribute_combination WHERE id_product_attribute = 41530;
DELETE FROM ps_product_attribute_shop WHERE id_product_attribute = 41530;
DELETE FROM ps_product_attribute_image WHERE id_product_attribute = 41530;
DELETE FROM ps_product_attribute WHERE id_product_attribute = 41530;
INSERT INTO ps_product_attribute (id_product_attribute, id_product, reference, supplier_reference, ean13, isbn, upc, mpn,
  wholesale_price, price, ecotax, weight, unit_price_impact, default_on, minimal_quantity, low_stock_threshold, low_stock_alert, available_date)
VALUES (41530, 1, 'ORACLE41530', '', '', '', '', '', 0, 0, 0, 0, 0, NULL, 1, NULL, 0, NULL);
INSERT INTO ps_product_attribute_shop (id_product, id_product_attribute, id_shop, wholesale_price, price, ecotax, weight,
  unit_price_impact, default_on, minimal_quantity, low_stock_threshold, low_stock_alert, available_date)
VALUES (1, 41530, 1, 0, 0, 0, 0, 0, NULL, 1, NULL, 0, NULL);
INSERT INTO ps_product_attribute_combination (id_attribute, id_product_attribute) VALUES (4, 41530), (10, 41530);
DELETE FROM ps_cart_product WHERE id_cart = 41530;
DELETE FROM ps_cart WHERE id_cart = 41530;
INSERT INTO ps_cart (id_cart, id_shop_group, id_shop, id_carrier, delivery_option, id_lang, id_address_delivery, id_address_invoice,
  id_currency, id_customer, id_guest, secure_key, recyclable, gift, gift_message, mobile_theme, allow_seperated_package, date_add, date_upd, checkout_session_data)
SELECT 41530, id_shop_group, id_shop, id_carrier, delivery_option, id_lang, id_address_delivery, id_address_invoice,
  id_currency, id_customer, id_guest, secure_key, recyclable, gift, gift_message, mobile_theme, allow_seperated_package, NOW(), NOW(), NULL
FROM ps_cart WHERE id_cart = 1;
INSERT INTO ps_cart_product (id_cart, id_product, id_address_delivery, id_shop, id_product_attribute, id_customization, quantity, date_add)
VALUES (41530, 1, 3, 1, 41530, 0, 1, NOW());
