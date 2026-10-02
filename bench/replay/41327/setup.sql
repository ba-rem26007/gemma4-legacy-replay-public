-- #41327 : simule un échec d'insertion d'une ligne de commande (ps_order_detail) pour le produit 8,
-- commandé en quantité 7 (marqueur propre au test), via un trigger MySQL (erreur SQL => Db::insert() renvoie false, PDO étant en mode silencieux).
UPDATE ps_configuration SET value = '0' WHERE name = 'PS_MULTISHOP_FEATURE_ACTIVE';
UPDATE ps_stock_available SET quantity = 300 WHERE id_product IN (1, 8);
DROP TRIGGER IF EXISTS bench41327_order_detail;
DELIMITER //
CREATE TRIGGER bench41327_order_detail BEFORE INSERT ON ps_order_detail FOR EACH ROW
BEGIN
  IF NEW.product_id = 8 AND NEW.product_quantity = 7 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'bench41327: order_detail insert refused';
  END IF;
END//
DELIMITER ;
-- Paniers non commandés du client de test vidés (évite le cumul des quantités entre deux passages)
DELETE cp FROM ps_cart_product cp JOIN ps_cart c ON c.id_cart = cp.id_cart
  WHERE c.id_customer = 2 AND c.id_cart NOT IN (SELECT id_cart FROM ps_orders);
DELETE FROM ps_cart WHERE id_customer = 2 AND id_cart NOT IN (SELECT id_cart FROM (SELECT id_cart FROM ps_orders) o);
