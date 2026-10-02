-- Issue #35888 : quantité minimale de vente = 3 sur un produit simple (Mug, id 6)
UPDATE ps_product SET minimal_quantity = 3 WHERE id_product = 6;
UPDATE ps_product_shop SET minimal_quantity = 3 WHERE id_product = 6;
