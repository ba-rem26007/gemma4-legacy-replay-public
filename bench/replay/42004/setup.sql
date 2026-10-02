-- #42004 : produit standard 942004 (clone du produit démo 8, sans règle de taxe : aucune n’existe dans cette install) nommé en ukrainien (84 caractères,
-- > 128 octets en UTF-8) ; suppression des copies créées par les exécutions précédentes.
SET SESSION sql_mode = '';
SET NAMES utf8mb4;
UPDATE ps_configuration SET value = '0' WHERE name = 'PS_MULTISHOP_FEATURE_ACTIVE';
CREATE TEMPORARY TABLE t_ids AS
  SELECT DISTINCT id_product FROM ps_product_lang WHERE name LIKE '%Персональна електронна%' OR id_product = 942004;
DELETE FROM ps_stock_available WHERE id_product IN (SELECT id_product FROM t_ids);
DELETE FROM ps_category_product WHERE id_product IN (SELECT id_product FROM t_ids);
DELETE FROM ps_product_shop WHERE id_product IN (SELECT id_product FROM t_ids);
DELETE FROM ps_product_lang WHERE id_product IN (SELECT id_product FROM t_ids);
DELETE FROM ps_product WHERE id_product IN (SELECT id_product FROM t_ids);

SET @p = 942004;
CREATE TEMPORARY TABLE t_p AS SELECT * FROM ps_product WHERE id_product = 8;
UPDATE t_p SET id_product = @p, reference = 'ORACLE42004', id_tax_rules_group = 0;
INSERT INTO ps_product SELECT * FROM t_p;
CREATE TEMPORARY TABLE t_pl AS SELECT * FROM ps_product_lang WHERE id_product = 8;
UPDATE t_pl SET id_product = @p,
  name = 'Персональна електронна обчислювальна машина АРМ фінансової та статистичної звітності',
  link_rewrite = 'oracle-42004';
INSERT INTO ps_product_lang SELECT * FROM t_pl;
CREATE TEMPORARY TABLE t_ps AS SELECT * FROM ps_product_shop WHERE id_product = 8;
UPDATE t_ps SET id_product = @p, id_tax_rules_group = 0;
INSERT INTO ps_product_shop SELECT * FROM t_ps;
INSERT INTO ps_category_product (id_category, id_product, position) VALUES (2, @p, 999);
INSERT INTO ps_stock_available (id_product, id_product_attribute, id_shop, id_shop_group, quantity, physical_quantity, reserved_quantity, depends_on_stock, out_of_stock, location)
  VALUES (@p, 0, 1, 0, 10, 10, 0, 0, 2, '');
