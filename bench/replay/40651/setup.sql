-- #40651 : noms internes des groupes d'attributs différents des noms publics,
-- et stock limité sur la déclinaison 1 du produit 1 pour provoquer le message d'erreur de quantité.
UPDATE ps_attribute_group_lang SET name = 'Taille interne' WHERE id_attribute_group = 1 AND id_lang = 1;
UPDATE ps_attribute_group_lang SET name = 'Couleur interne' WHERE id_attribute_group = 2 AND id_lang = 1;
UPDATE ps_attribute_group_lang SET public_name = 'Taille' WHERE id_attribute_group = 1 AND id_lang = 1;
UPDATE ps_attribute_group_lang SET public_name = 'Couleur' WHERE id_attribute_group = 2 AND id_lang = 1;
UPDATE ps_stock_available SET quantity = 1, out_of_stock = 0 WHERE id_product = 1 AND id_product_attribute = 1;
