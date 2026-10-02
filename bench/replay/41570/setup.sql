-- #41570 : supprime le tri mémorisé de la grille des caractéristiques pour retomber sur le tri par défaut
DELETE FROM ps_admin_filter WHERE filter_id = 'feature';
