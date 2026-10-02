-- On s'assure qu'une catégorie spécifique est enfant de la catégorie 3 (Vêtements)
-- On utilise l'ID 999 pour éviter les conflits avec les données de démo
INSERT INTO ps_category (id_category, id_parent, id_shop_default, active, date_add, date_upd, level_depth, nleft, nright)
VALUES (999, 3, 1, 1, NOW(), NOW(), 2, 0, 0) 
ON DUPLICATE KEY UPDATE id_parent = 3, active = 1;

INSERT INTO ps_category_lang (id_category, name, link_rewrite, id_lang)
VALUES (999, 'SUB_CATEGORY_EXPORT_TEST', 'sub-category-export-test', 1) 
ON DUPLICATE KEY UPDATE name = 'SUB_CATEGORY_EXPORT_TEST', link_rewrite = 'sub-category-export-test';

INSERT INTO ps_category_shop (id_category, id_shop)
VALUES (999, 1) 
ON DUPLICATE KEY UPDATE id_shop = 1;
