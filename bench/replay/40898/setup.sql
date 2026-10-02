-- Multiboutique : 2 boutiques dans le groupe par défaut, qui partage les quantités disponibles
DELETE FROM ps_configuration WHERE name = 'PS_MULTISHOP_FEATURE_ACTIVE';
INSERT INTO ps_configuration (id_shop_group, id_shop, name, value, date_add, date_upd)
VALUES (NULL, NULL, 'PS_MULTISHOP_FEATURE_ACTIVE', '1', NOW(), NOW());
INSERT IGNORE INTO ps_shop (id_shop, id_shop_group, name, color, id_category, theme_name, active, deleted)
VALUES (2, 1, 'Boutique2', '', 2, 'hummingbird', 1, 0);
INSERT IGNORE INTO ps_shop_url (id_shop_url, id_shop, domain, domain_ssl, physical_uri, virtual_uri, main, active)
VALUES (2, 2, 'localhost', 'localhost', '/', 'shop2/', 1, 1);
INSERT IGNORE INTO ps_lang_shop (id_lang, id_shop) SELECT id_lang, 2 FROM ps_lang;
INSERT IGNORE INTO ps_currency_shop (id_currency, id_shop, conversion_rate) SELECT id_currency, 2, conversion_rate FROM ps_currency_shop WHERE id_shop = 1;
INSERT IGNORE INTO ps_employee_shop (id_employee, id_shop) SELECT id_employee, 2 FROM ps_employee;
UPDATE ps_shop_group SET share_stock = 1 WHERE id_shop_group = 1;
-- Stock partagé : lignes stock_available portées par le groupe (id_shop = 0)
UPDATE ps_stock_available SET id_shop = 0, id_shop_group = 1 WHERE id_shop = 1;
UPDATE ps_stock_available SET reserved_quantity = 0, physical_quantity = 0 WHERE id_product = 8;
-- Commande de démo n° 2 (dont 1 × « Mug Today is a good day ») remise « En attente du paiement par chèque »
UPDATE ps_orders SET current_state = 1, valid = 0 WHERE id_order = 2;
DELETE FROM ps_order_history WHERE id_order = 2 AND id_order_state <> 1;
