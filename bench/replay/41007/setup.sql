-- Active la page Pays migrée (Symfony grid) : feature flag « country »
UPDATE ps_feature_flag SET state = 1 WHERE name = 'country';
