<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29530, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);
Context::getContext()->currency = new Currency(1);

// Convert PHP Notices/Warnings to Exceptions to detect the "Trying to access array offset on value of type bool" bug
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

try {
    // 1. Use existing Order 1 or create it
    $order = new Order(1);
    if (!Validate::isLoadedObject($order)) {
        $order = new Order();
        $order->id_address_invoice = 1;
        $order->id_cart = 1;
        $order->id_currency = 1;
        $order->id_lang = 1;
        $order->id_customer = 1;
        $order->id_carrier = 1;
        $order->payment = 'Payment';
        $order->module = 'ps_checkpayment';
        $order->total_paid = 10;
        $order->total_paid_real = 10;
        $order->total_products = 10;
        $order->total_products_wt = 10;
        $order->conversion_rate = 1;
        $order->add();
    }

    // 2. Create an OrderSlip
    $os = new OrderSlip();
    $os->id_order = (int)$order->id;
    $os->id_customer = (int)$order->id_customer; 
    $os->conversion_rate = 1;
    $os->total_products_tax_excl = 10;
    $os->total_products_tax_incl = 12;
    $os->total_shipping_tax_excl = 0;
    $os->total_shipping_tax_incl = 0;
    $os->add();

    // 3. Create a "broken" OrderSlipDetail
    // Point to an id_order_detail that does NOT exist in ps_order_detail.
    $fakeOrderDetailId = 999999; 
    Db::getInstance()->insert('order_slip_detail', [
        'id_order_slip' => (int)$os->id,
        'id_order_detail' => (int)$fakeOrderDetailId,
        'product_quantity' => 1,
        'unit_price_tax_excl' => 10,
        'unit_price_tax_incl' => 12,
        'total_price_tax_excl' => 10,
        'total_price_tax_incl' => 12,
        'amount_tax_excl' => 0,
        'amount_tax_incl' => 0,
    ]);

    echo "OrderSlip created with ID: " . $os->id . " pointing to non-existent OrderDetail: $fakeOrderDetailId\n";

    // 4. Call the method that triggers the bug
    // Before fix: Db::getRow returns false, accessing $row['rate'] triggers a Notice.
    // With our error handler, this Notice becomes an ErrorException.
    $result = $os->getEcoTaxTaxesBreakdown();
    
    echo "Method executed successfully without notices. Result count: " . count($result) . "\n";
    exit(0);

} catch (\Throwable $t) {
    echo "Caught expected bug: " . $t->getMessage() . "\n";
    exit(1);
} finally {
    restore_error_handler();
}
