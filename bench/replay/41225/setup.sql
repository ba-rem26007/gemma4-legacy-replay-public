-- Place le groupe Couleur (2) avant le groupe Taille (1) dans l'ordre des groupes d'attributs
UPDATE ps_attribute_group SET position = 0 WHERE id_attribute_group = 2;
UPDATE ps_attribute_group SET position = 1 WHERE id_attribute_group = 1;
