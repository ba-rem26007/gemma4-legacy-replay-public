<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #36123, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);
Context::getContext()->currency = new Currency(1);

$id_product = 1;
$id_order = 1;
$initial_qty = 15;

// 1. Prepare Product and Stock
StockAvailable::setQuantity($id_product, 0, $initial_qty);

// 2. Prepare Order
$order = new Order($id_order);
if (!Validate::isLoadedObject($order)) {
    $order = new Order();
    $order->id_address_invoice = 1;
    $order->id_address_delivery = 1;
    $order->id_cart = 1;
    $order->id_currency = 1;
    $order->id_lang = 1;
    $order->id_customer = 1;
    $order->id_carrier = 1;
    $order->payment = 'Bankwire';
    $order->module = 'ps_checkpayment';
    $order->total_paid = 10;
    $order->total_paid_real = 10;
    $order->total_products = 10;
    $order->total_products_wt = 10;
    $order->conversion_rate = 1;
    $order->id_shop = 1;
    $order->add();
}

// Clear existing details and add Product 1 to Order 1 with all required fields
Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'order_detail WHERE id_order = ' . (int)$id_order);
$od = new OrderDetail();
$od->id_order = (int)$id_order;
$od->product_id = (int)$id_product;
$od->product_attribute_id = 0;
$od->product_quantity = 1;
$od->product_price = 10;
$od->id_shop = (int)$order->id_shop;
$od->product_name = 'Test Product';
$od->product_weight = 0;
$od->id_tax_rate = 0;
$od->add();

// 3. Define States
$os_bank = (int)Configuration::get('PS_OS_BANKWIRE');
$os_cancel = (int)Configuration::get('PS_OS_CANCELED');

// 4. Execution sequence
// Step 1: Set order to "Awaiting bank transfer payment"
$order->current_state = $os_bank;
$order->update();

$phys_start = (int)Db::getInstance()->getValue('SELECT quantity FROM ' . _DB_PREFIX_ . 'stock_available WHERE id_product = ' . (int)$id_product . ' AND id_product_attribute = 0');
echo "Initial physical quantity: $phys_start\n";

$oh = new OrderHistory();

// Step 3: Cancel the order
$oh->changeIdOrderState($os_cancel, $id_order);
$phys_after_cancel = (int)Db::getInstance()->getValue('SELECT quantity FROM ' . _DB_PREFIX_ . 'stock_available WHERE id_product = ' . (int)$id_product . ' AND id_product_attribute = 0');
echo "Physical quantity after cancellation: $phys_after_cancel\n";

// Step 5: Set the order status back to "Awaiting bank transfer payment"
$oh->changeIdOrderState($os_bank, $id_order);
$phys_final = (int)Db::getInstance()->getValue('SELECT quantity FROM ' . _DB_PREFIX_ . 'stock_available WHERE id_product = ' . (int)$id_product . ' AND id_product_attribute = 0');
echo "Final physical quantity: $phys_final\n";

// The bug causes the physical quantity to increase (e.g., 15 -> 16)
if ($phys_final > $initial_qty) {
    echo "BUG: Physical quantity increased incorrectly to $phys_final\n";
    exit(1);
}

if ($phys_final === $initial_qty) {
    echo "SUCCESS: Physical quantity remained at $phys_final\n";
    exit(0);
}

echo "Unexpected physical quantity: $phys_final\n";
exit(1);
