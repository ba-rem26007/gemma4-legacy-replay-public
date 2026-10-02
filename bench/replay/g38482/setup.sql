-- Rendre le produit 1 virtuel pour toutes les boutiques
UPDATE ps_product SET is_virtual = 1 WHERE id_product = 1;
UPDATE ps_product_shop SET is_virtual = 1 WHERE id_product = 1;

-- S'assurer que la commande 1 ne contient que le produit virtuel 1
DELETE FROM ps_order_detail WHERE id_order = 1 AND product_id != 1;

-- Passer la commande 1 à l'état "Paiement accepté" (id_order_state = 2) pour permettre la facturation
UPDATE ps_orders SET current_state = 2 WHERE id_order = 1;
