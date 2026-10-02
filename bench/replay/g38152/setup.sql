-- Désactivation des notifications pour éviter les requêtes AJAX en arrière-plan
-- qui pourraient masquer ou déclencher le spinner de manière aléatoire.
UPDATE ps_configuration SET value = '0' WHERE name = 'PS_NOTIFICATIONS_ENABLED';
