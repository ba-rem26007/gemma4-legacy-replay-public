-- #40999 : repart d'une boutique unique, multiboutique désactivé (le test l'active via l'interface) ;
-- supprime les boutiques créées par les passages précédents (et la boutique de test éventuelle d'autres bugs).
DELETE FROM ps_shop_url WHERE id_shop > 1;
DELETE FROM ps_shop WHERE id_shop > 1;
UPDATE ps_configuration SET value = '0' WHERE name = 'PS_MULTISHOP_FEATURE_ACTIVE';
UPDATE ps_tab SET active = 0 WHERE class_name IN ('AdminShopGroup', 'AdminShopUrl');
