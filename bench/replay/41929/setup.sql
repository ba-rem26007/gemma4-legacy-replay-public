-- #41929 : fonctionnalité expérimentale « Catalog price rules » activée
UPDATE ps_feature_flag SET state = 1 WHERE name = 'catalog_price_rule';
UPDATE ps_configuration SET value = '0' WHERE name = 'PS_MULTISHOP_FEATURE_ACTIVE';
