-- Multiboutique minimal : activation de la fonctionnalité + 2e boutique dans le groupe par défaut
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
-- Supprime les surcharges de schéma d'URL par boutique laissées par un run précédent
DELETE FROM ps_configuration WHERE name LIKE 'PS_ROUTE_%' AND (id_shop IS NOT NULL OR id_shop_group IS NOT NULL);
