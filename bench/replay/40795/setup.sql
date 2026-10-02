-- #40795 : remise à zéro de l'erreur mémorisée par le module de test benchcartsave.
DELETE FROM ps_configuration WHERE name = 'BENCHCARTSAVE_ERROR';
