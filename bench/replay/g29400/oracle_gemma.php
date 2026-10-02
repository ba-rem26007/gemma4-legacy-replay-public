<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29400, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Test for Ticket: Server error when quickview product quantity changed
 * The bug is that ProductController::getIdProductAttributeByGroup() does not catch 
 * the PrestaShopObjectNotFoundException thrown by Product::getIdProductAttributeByIdAttributes()
 * when an invalid combination group is provided.
 */

// Setup context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);
Context::getContext()->currency = new Currency(1);

// Use a product from demo data
$idProduct = 1;
$product = new Product($idProduct);

if (!Validate::isLoadedObject($product)) {
    // Fallback: create a product if 1 doesn't exist
    $product = new Product();
    $product->price = 10.0;
    $product->name = [1 => 'Test Product'];
    $product->link_rewrite = [1 => 'test-product'];
    $product->add();
    $idProduct = $product->id;
}

// Trigger: provide an invalid group ID in the request
$_GET['group'] = '999999';

// Instantiate the controller
$controller = new ProductController();

// The 'product' property is protected in ProductController, we must use Reflection to set it
try {
    $refProp = new ReflectionProperty('ProductController', 'product');
    $refProp->setAccessible(true);
    $refProp->setValue($controller, $product);
} catch (\Throwable $e) {
    echo "Failed to set protected property 'product': " . $e->getMessage() . "\n";
    exit(1);
}

echo "Testing Product ID: $idProduct with invalid group 999999\n";

try {
    // Use Reflection to access the private method getIdProductAttributeByGroup
    $refMethod = new ReflectionMethod('ProductController', 'getIdProductAttributeByGroup');
    $refMethod->setAccessible(true);

    echo "Calling getIdProductAttributeByGroup()...\n";
    $result = $refMethod->invoke($controller);
    
    echo "Result observed: " . var_export($result, true) . "\n";

    // After fix, it should return 0 instead of throwing an exception
    if ($result === 0) {
        echo "Success: Exception was caught and 0 was returned.\n";
        exit(0);
    } else {
        echo "Failure: Expected 0, got " . var_export($result, true) . "\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Caught exception (Bug present): " . get_class($t) . " - " . $t->getMessage() . "\n";
    // If we are here, the exception was NOT caught by the controller, meaning the bug is still there.
    exit(1);
}
