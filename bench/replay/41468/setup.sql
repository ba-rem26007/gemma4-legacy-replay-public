-- #41468 : multiboutique (2 boutiques) + produit 941468 à déclinaisons (clone du produit démo 1),
-- présent dans les 2 boutiques, déclinaison par défaut 941468 (cache_default_attribute dans les 2 lignes product_shop)
INSERT IGNORE INTO ps_shop (id_shop, id_shop_group, name, color, id_category, theme_name, active, deleted)
  VALUES (2, 1, 'Boutique 2', '', 2, 'hummingbird', 1, 0);
INSERT IGNORE INTO ps_shop_url (id_shop_url, id_shop, domain, domain_ssl, physical_uri, virtual_uri, main, active)
  VALUES (2, 2, 'oracle-shop2.invalid', 'oracle-shop2.invalid', '/', 'shop2/', 1, 1);
DELETE FROM ps_configuration WHERE name = 'PS_MULTISHOP_FEATURE_ACTIVE';
INSERT INTO ps_configuration (id_shop_group, id_shop, name, value, date_add, date_upd)
  VALUES (NULL, NULL, 'PS_MULTISHOP_FEATURE_ACTIVE', '1', NOW(), NOW());

SET SESSION sql_mode = '';
SET @p = 941468;
DELETE FROM ps_product_attribute_combination WHERE id_product_attribute IN (SELECT id_product_attribute FROM ps_product_attribute WHERE id_product = @p);
DELETE FROM ps_product_attribute_shop WHERE id_product = @p;
DELETE FROM ps_product_attribute WHERE id_product = @p;
DELETE FROM ps_stock_available WHERE id_product = @p;
DELETE FROM ps_category_product WHERE id_product = @p;
DELETE FROM ps_product_shop WHERE id_product = @p;
DELETE FROM ps_product_lang WHERE id_product = @p;
DELETE FROM ps_product WHERE id_product = @p;

CREATE TEMPORARY TABLE t_p AS SELECT * FROM ps_product WHERE id_product = 1;
UPDATE t_p SET id_product = @p, reference = 'ORACLE41468', cache_default_attribute = 941468, product_type = 'combinations';
INSERT INTO ps_product SELECT * FROM t_p;
CREATE TEMPORARY TABLE t_pl AS SELECT * FROM ps_product_lang WHERE id_product = 1 AND id_shop = 1;
UPDATE t_pl SET id_product = @p, name = 'Oracle 41468', link_rewrite = 'oracle-41468';
INSERT INTO ps_product_lang SELECT * FROM t_pl;
UPDATE t_pl SET id_shop = 2;
INSERT INTO ps_product_lang SELECT * FROM t_pl;
CREATE TEMPORARY TABLE t_ps AS SELECT * FROM ps_product_shop WHERE id_product = 1 AND id_shop = 1;
UPDATE t_ps SET id_product = @p, cache_default_attribute = 941468;
INSERT INTO ps_product_shop SELECT * FROM t_ps;
UPDATE t_ps SET id_shop = 2;
INSERT INTO ps_product_shop SELECT * FROM t_ps;
INSERT INTO ps_category_product (id_category, id_product, position) VALUES (2, @p, 999), (4, @p, 999);

INSERT INTO ps_product_attribute (id_product_attribute, id_product, reference, default_on, minimal_quantity)
  VALUES (941468, @p, 'ORACLE41468-S', 1, 1), (941469, @p, 'ORACLE41468-M', NULL, 1);
INSERT INTO ps_product_attribute_shop (id_product, id_product_attribute, id_shop, default_on, minimal_quantity)
  VALUES (@p, 941468, 1, 1, 1), (@p, 941469, 1, NULL, 1), (@p, 941468, 2, 1, 1), (@p, 941469, 2, NULL, 1);
INSERT INTO ps_product_attribute_combination (id_attribute, id_product_attribute) VALUES (1, 941468), (2, 941469);
INSERT INTO ps_stock_available (id_product, id_product_attribute, id_shop, id_shop_group, quantity, physical_quantity, reserved_quantity, depends_on_stock, out_of_stock, location)
  VALUES (@p, 0, 1, 0, 20, 0, 0, 0, 2, ''), (@p, 941468, 1, 0, 10, 0, 0, 0, 2, ''), (@p, 941469, 1, 0, 10, 0, 0, 0, 2, ''),
         (@p, 0, 2, 0, 20, 0, 0, 0, 2, ''), (@p, 941468, 2, 0, 10, 0, 0, 0, 2, ''), (@p, 941469, 2, 0, 10, 0, 0, 0, 2, '');
