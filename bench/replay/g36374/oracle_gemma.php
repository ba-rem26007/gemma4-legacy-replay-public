<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #36374, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Define the interface manually to avoid "Interface not found" errors in CLI 
 * if the autoloader fails to find it.
 */
if (!interface_exists('PrestaShop\PrestaShop\Core\Context\ContextStateManagerInterface')) {
    eval('namespace PrestaShop\PrestaShop\Core\Context { 
        interface ContextStateManagerInterface { 
            public function saveCurrentContext(); 
            public function restorePreviousContext(); 
        } 
    }');
}

use PrestaShop\PrestaShop\Core\Context\ContextStateManagerInterface;
use PrestaShop\PrestaShop\Adapter\Order\OrderDetailUpdater;

class MockContextStateManager implements ContextStateManagerInterface
{
    public function saveCurrentContext() {}
    public function restorePreviousContext() {}
}

try {
    // Setup Context
    Context::getContext()->shop = new Shop(1);
    Context::getContext()->language = new Language(1);
    Context::getContext()->currency = new Currency(1);

    // Ensure Product 1 exists and has a tax rule group
    $product = new Product(1);
    if (!Validate::isLoadedObject($product)) {
        echo "Product 1 not found\n";
        exit(1);
    }
    $product->id_tax_rules_group = 1; 
    $product->save();

    // Use existing Order 1
    $order = new Order(1);
    if (!Validate::isLoadedObject($order)) {
        echo "Order 1 not found\n";
        exit(1);
    }

    // Ensure delivery address is set for tax calculation
    $order->id_address_delivery = 1;
    $order->update();

    // We need an OrderDetail linked to this order for the updater to process it.
    // Order::getProducts() queries the ps_order_detail table.
    $products = $order->getProducts();
    if (empty($products)) {
        $orderDetail = new OrderDetail();
        $orderDetail->id_order = (int)$order->id;
        $orderDetail->id_product = 1;
        $orderDetail->product_name = 'Test Product';
        $orderDetail->product_quantity = 1;
        $orderDetail->product_price = 10.00;
        $orderDetail->id_shop = 1;
        $orderDetail->id_warehouse = 0;
        if (!$orderDetail->add()) {
            echo "Failed to create OrderDetail\n";
            exit(1);
        }
    } else {
        $orderDetail = new OrderDetail((int)$products[0]['id_order_detail']);
    }
    
    if (!Validate::isLoadedObject($orderDetail)) {
        echo "Failed to load OrderDetail\n";
        exit(1);
    }

    // Set a known base price to make calculations predictable.
    // We set tax_incl = tax_excl to ensure that the bug (which uses +=) is detectable.
    $basePriceExcl = 10.00;
    $qty = 1;
    $orderDetail->unit_price_tax_excl = $basePriceExcl;
    $orderDetail->total_price_tax_excl = $basePriceExcl * $qty;
    $orderDetail->product_quantity = $qty;
    $orderDetail->unit_price_tax_incl = $basePriceExcl;
    $orderDetail->total_price_tax_incl = $basePriceExcl;
    $orderDetail->update();

    // Calculate expected tax based on PrestaShop's tax rules for this product/address
    $taxRate = Tax::getProductTaxRate(1, 1); 
    $expectedTaxAmount = $basePriceExcl * ($taxRate / 100);
    $expectedTotalTaxIncl = $basePriceExcl + $expectedTaxAmount;

    echo "Base Price Excl: $basePriceExcl\n";
    echo "Tax Rate: $taxRate%\n";
    echo "Expected Total Tax Incl: $expectedTotalTaxIncl\n";

    // Instantiate the updater
    $updater = new OrderDetailUpdater(new MockContextStateManager());
    
    // Trigger the bug: update taxes for the order
    $updater->updateOrderDetailsTaxes($order);

    // Reload OrderDetail from DB to see the result of the update
    $orderDetail = new OrderDetail($orderDetail->id);
    $observedTotalTaxIncl = (float)$orderDetail->total_price_tax_incl;

    echo "Observed Total Tax Incl: $observedTotalTaxIncl\n";

    // The bug adds the tax amount to the existing total_price_tax_incl twice.
    // If fixed, observed should match expected.
    if (abs($observedTotalTaxIncl - $expectedTotalTaxIncl) < 0.01) {
        echo "SUCCESS: Price calculation is correct.\n";
        exit(0);
    } else {
        echo "FAILURE: Price calculation is incorrect. Observed: $observedTotalTaxIncl, Expected: $expectedTotalTaxIncl\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
