-- #41412 : e-mail de la boutique ASCII (le test envoie l'e-mail de test vers une adresse IDN).
SET NAMES utf8mb4;
UPDATE ps_configuration SET value = '0' WHERE name = 'PS_MULTISHOP_FEATURE_ACTIVE';
UPDATE ps_configuration SET value = 'demo@prestashop.com' WHERE name = 'PS_SHOP_EMAIL';
