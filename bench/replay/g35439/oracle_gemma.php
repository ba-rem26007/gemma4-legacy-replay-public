<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #35439, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context to avoid crashes in AddressFormat or Translator
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

/**
 * Mock Smarty to avoid template loading errors in CLI
 */
class MockSmarty {
    public function assign($data, $value = null) {
        return true;
    }
    public function fetch($template, $cache_id = null) {
        return '';
    }
}

try {
    // 1. Ensure basic demo data exists to avoid AddressFormat crashes
    $id_order = 1;
    $order = new Order($id_order);
    if (!Validate::isLoadedObject($order)) {
        echo "Order 1 not found\n";
        exit(1);
    }

    // Ensure the order has a valid invoice address
    if (!$order->id_address_invoice || !Validate::isLoadedObject(new Address($order->id_address_invoice))) {
        $order->id_address_invoice = 1;
        $order->update();
    }
    
    // Ensure the address has a valid country
    $address = new Address($order->id_address_invoice);
    if (!$address->id_country || !Validate::isLoadedObject(new Country($address->id_country))) {
        $address->id_country = 1;
        $address->update();
    }

    // 2. Create an OrderSlip that refunds ONLY delivery fees
    // amount = 0 triggers the 'else' block in HTMLTemplateOrderSlip::getContent()
    // We use Db directly to avoid any potential validation crashes during OrderSlip->add()
    $os_id = (int)Db::getInstance()->insert('order_slip', [
        'id_customer' => (int)$order->id_customer,
        'id_order' => (int)$id_order,
        'amount' => 0.000000,
        'shipping_cost' => 1,
        'shipping_cost_amount' => 10.000000,
        'partial' => 1,
        'date_add' => date('Y-m-d H:i:s'),
        'date_upd' => date('Y-m-d H:i:s'),
        'conversion_rate' => 1.000000,
    ]);

    if (!$os_id) {
        echo "Failed to create OrderSlip via DB\n";
        exit(1);
    }

    $os = new OrderSlip($os_id);

    // 3. Instantiate the PDF template
    $smarty = new MockSmarty();
    $pdf = new HTMLTemplateOrderSlip($os, $smarty);

    // 4. Execute the method containing the bug
    // Before fix: if amount == 0, $this->order->products is set to null
    // After fix: if amount == 0, $this->order->products is set to []
    $pdf->getContent();

    $products = $pdf->order->products;

    echo "Value of order->products: " . (is_null($products) ? 'NULL' : (is_array($products) ? 'ARRAY' : gettype($products))) . "\n";

    if (is_null($products)) {
        echo "Bug detected: order->products is NULL\n";
        exit(1);
    } elseif (is_array($products)) {
        echo "Fixed: order->products is an ARRAY\n";
        exit(0);
    } else {
        echo "Unexpected type: " . gettype($products) . "\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Exception caught: " . $t->getMessage() . "\n";
    echo "File: " . $t->getFile() . " Line: " . $t->getLine() . "\n";
    exit(1);
}
