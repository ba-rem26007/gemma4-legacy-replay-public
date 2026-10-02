-- #41735 : valeur PERSONNALISÉE (custom=1) « Oracle perso 41735 » pour la caractéristique 1 (Composition),
-- rattachée au produit 1 comme le fait la page produit ; filtres de grille mémorisés supprimés.
DELETE FROM ps_feature_product WHERE id_feature_value = 941735;
DELETE FROM ps_feature_value_lang WHERE id_feature_value = 941735;
DELETE FROM ps_feature_value WHERE id_feature_value = 941735;
INSERT INTO ps_feature_value (id_feature_value, id_feature, custom) VALUES (941735, 1, 1);
INSERT INTO ps_feature_value_lang (id_feature_value, id_lang, value) VALUES (941735, 1, 'Oracle perso 41735'), (941735, 2, 'Oracle perso 41735');
INSERT INTO ps_feature_product (id_feature, id_product, id_feature_value) VALUES (1, 1, 941735);
DELETE FROM ps_admin_filter WHERE filter_id IN ('feature', 'feature_value');
