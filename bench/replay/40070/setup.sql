-- #40070 : remise à zéro de la trace laissée par le module de test benchcarrierhook.
DELETE FROM ps_configuration WHERE name = 'BENCHCARRIERHOOK_LAST';
