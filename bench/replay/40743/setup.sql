-- #40743 : factures désactivées + commande 5 (virement bancaire) remise dans l'état « En attente de virement », sans paiement ni facture.
SET SESSION sql_mode = "";
UPDATE ps_configuration SET value = '0' WHERE name = 'PS_INVOICE';
DELETE FROM ps_order_invoice_payment WHERE id_order = 5;
DELETE FROM ps_order_payment WHERE order_reference = (SELECT reference FROM ps_orders WHERE id_order = 5);
DELETE FROM ps_order_invoice_tax WHERE id_order_invoice IN (SELECT id_order_invoice FROM ps_order_invoice WHERE id_order = 5);
DELETE FROM ps_order_invoice WHERE id_order = 5;
UPDATE ps_order_detail SET id_order_invoice = 0 WHERE id_order = 5;
UPDATE ps_order_carrier SET id_order_invoice = 0 WHERE id_order = 5;
DELETE FROM ps_order_history WHERE id_order = 5 AND id_order_state <> 10;
UPDATE ps_orders SET current_state = 10, valid = 0, invoice_number = 0, invoice_date = '0000-00-00 00:00:00', delivery_number = 0, total_paid_real = 0 WHERE id_order = 5;
