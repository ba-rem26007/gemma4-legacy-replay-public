-- #40971 : active le multiboutique (une seule boutique, groupe « Default ») et remet le logo d'en-tête
-- global sur logo.png, sans valeur spécifique au groupe ou à la boutique.
DELETE FROM ps_configuration WHERE name = 'PS_MULTISHOP_FEATURE_ACTIVE';
INSERT INTO ps_configuration (id_shop_group, id_shop, name, value, date_add, date_upd) VALUES (NULL, NULL, 'PS_MULTISHOP_FEATURE_ACTIVE', '1', NOW(), NOW());
DELETE FROM ps_configuration WHERE name = 'PS_LOGO' AND (id_shop IS NOT NULL OR id_shop_group IS NOT NULL);
UPDATE ps_configuration SET value = 'logo.png' WHERE name = 'PS_LOGO';
-- Deuxième boutique minimale dans le même groupe, pour que le sélecteur de contexte multiboutique soit actif
INSERT IGNORE INTO ps_shop (id_shop, id_shop_group, name, color, id_category, theme_name, active, deleted) VALUES (2, 1, 'Bench shop 2', '', 2, 'classic', 1, 0);
INSERT IGNORE INTO ps_shop_url (id_shop_url, id_shop, domain, domain_ssl, physical_uri, virtual_uri, main, active) SELECT 2, 2, domain, domain_ssl, physical_uri, 'shop2/', 1, 1 FROM ps_shop_url WHERE id_shop = 1 AND main = 1 LIMIT 1;
