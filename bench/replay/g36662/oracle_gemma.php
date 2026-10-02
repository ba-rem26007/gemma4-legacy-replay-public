<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #36662, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Test for Ticket: Problems OrderProductForViewing "must be of type string (or int), null given"
 * The bug occurs when StockAvailable::getLocation returns null (specifically with Memcached).
 * The fix casts the result to (string), ensuring that null or false becomes an empty string.
 */

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

// Use existing Order 1
$order = new Order(1);
if (!Validate::isLoadedObject($order)) {
    echo "Order 1 not found in demo data\n";
    exit(1);
}

// Identify a product in the order to simulate its disappearance from the catalog
$details = $order->getProductsDetail();
if (empty($details)) {
    echo "Order 1 has no products\n";
    exit(1);
}
$firstProduct = reset($details);
$idProduct = (int)$firstProduct['product_id'];

// To trigger the bug (or the case where getLocation returns false/null), 
// we delete the product from the catalog.
// StockAvailable::getLocation will then fail to find the product and return false (or null if cached).
$p = new Product($idProduct);
$p->delete();

try {
    // Call the method touched by the fix
    $products = $order->getProducts();
    
    $foundTargetProduct = false;
    foreach ($products as $prod) {
        if ((int)$prod['product_id'] === $idProduct) {
            $foundTargetProduct = true;
            $location = $prod['location'];
            
            echo "Product ID: $idProduct\n";
            echo "Location value: " . var_export($location, true) . "\n";
            echo "Location type: " . gettype($location) . "\n";
            
            // The fix is to ensure the location is ALWAYS a string.
            // Before fix: it could be null (with Memcached) or false (without).
            // After fix: it must be a string (empty string if not found).
            if (!is_string($location)) {
                echo "FAIL: location is not a string\n";
                exit(1);
            }
            
            if ($location === null) {
                echo "FAIL: location is NULL\n";
                exit(1);
            }
        }
    }

    if (!$foundTargetProduct) {
        echo "Target product not found in Order::getProducts() result\n";
        exit(1);
    }

    echo "SUCCESS: location is a string\n";
    exit(0);

} catch (\Throwable $t) {
    echo "Fatal error caught: " . $t->getMessage() . "\n";
    exit(1);
}
