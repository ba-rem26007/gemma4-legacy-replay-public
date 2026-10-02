-- #41130 : multiboutique actif (2 boutiques) + client Admin API (secret connu, scope zone_write)
INSERT IGNORE INTO ps_shop (id_shop, id_shop_group, name, color, id_category, theme_name, active, deleted)
  VALUES (2, 1, 'Boutique 2', '', 2, 'hummingbird', 1, 0);
INSERT IGNORE INTO ps_shop_url (id_shop_url, id_shop, domain, domain_ssl, physical_uri, virtual_uri, main, active)
  VALUES (2, 2, 'oracle-shop2.invalid', 'oracle-shop2.invalid', '/', 'shop2/', 1, 1);
UPDATE ps_shop_url SET domain = 'oracle-shop2.invalid', domain_ssl = 'oracle-shop2.invalid' WHERE id_shop_url = 2;
DELETE FROM ps_configuration WHERE name = 'PS_MULTISHOP_FEATURE_ACTIVE';
INSERT INTO ps_configuration (id_shop_group, id_shop, name, value, date_add, date_upd)
  VALUES (NULL, NULL, 'PS_MULTISHOP_FEATURE_ACTIVE', '1', NOW(), NOW());
UPDATE ps_configuration SET value = '1' WHERE name = 'PS_ENABLE_ADMIN_API';
UPDATE ps_feature_flag SET state = 1 WHERE name = 'admin_api_multistore';
DELETE FROM ps_api_client WHERE client_id = 'oracle-41130';
INSERT INTO ps_api_client (client_id, client_name, client_secret, enabled, scopes, description, lifetime)
  VALUES ('oracle-41130', 'oracle-41130', '$2y$10$ErvrJhZopprRsGATDoype.3BMYbD92muqikJRbAhXdnEeYiAdwbbC', 1,
          '["zone_write","zone_read"]', 'oracle', 3600);
DELETE zs FROM ps_zone_shop zs JOIN ps_zone z ON z.id_zone = zs.id_zone WHERE z.name LIKE 'Oracle41130%';
DELETE FROM ps_zone WHERE name LIKE 'Oracle41130%';
