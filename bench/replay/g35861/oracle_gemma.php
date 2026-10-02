<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #35861, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);
Context::getContext()->currency = new Currency(1);

try {
    // Use existing demo data
    $order = new Order(1);
    $customer = new Customer(1);

    if (!Validate::isLoadedObject($order) || !Validate::isLoadedObject($customer)) {
        echo "Demo data Order(1) or Customer(1) not found.\n";
        exit(1);
    }

    // Create an OrderSlip that only refunds shipping costs
    // This triggers the bug: partial = 1, but no entries in ps_order_slip_detail
    $orderSlip = new OrderSlip();
    $orderSlip->id_order = (int)$order->id;
    $orderSlip->id_customer = (int)$customer->id;
    $orderSlip->amount = 10.00;
    $orderSlip->shipping_cost = 1;
    $orderSlip->shipping_cost_amount = 10.00;
    $orderSlip->partial = 1;
    $orderSlip->conversion_rate = 1.000000;
    $orderSlip->total_products_tax_excl = 0;
    $orderSlip->total_products_tax_incl = 0;
    $orderSlip->total_shipping_tax_excl = 0;
    $orderSlip->total_shipping_tax_incl = 0;
    $orderSlip->order_slip_type = 0;
    $orderSlip->date_add = date('Y-m-d H:i:s');
    $orderSlip->date_upd = date('Y-m-d H:i:s');
    
    if (!$orderSlip->add()) {
        echo "Failed to create OrderSlip.\n";
        exit(1);
    }

    echo "OrderSlip created with ID: " . $orderSlip->id . " (Partial=1, Shipping only)\n";

    // Instantiate the PDF template
    $smarty = Context::getContext()->smarty;
    $template = new HTMLTemplateOrderSlip($orderSlip, $smarty);

    echo "Calling getContent()...\n";
    // This method triggers the Sorter::natural() call on $this->order->products
    // Before fix: $this->order->products is set to null, causing TypeError in Sorter::natural()
    // After fix: $this->order->products is set to [], which Sorter::natural() accepts
    $content = $template->getContent();
    
    echo "Success: getContent() returned without exception.\n";
    exit(0);

} catch (\TypeError $e) {
    echo "Caught TypeError: " . $e->getMessage() . "\n";
    // The bug is a TypeError in Sorter::natural() when receiving null
    if (strpos($e->getMessage(), 'Sorter::natural()') !== false && strpos($e->getMessage(), 'must be of type array, null given') !== false) {
        echo "Bug reproduced: Sorter::natural received null instead of array.\n";
        exit(1);
    }
    exit(1);
} catch (\Throwable $t) {
    echo "Unexpected error: " . $t->getMessage() . "\n";
    exit(1);
}
