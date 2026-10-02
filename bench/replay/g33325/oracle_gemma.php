<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33325, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\Presenter\ManufacturerPresenter;

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);
// Context::getContext()->cookie is already initialized by config.inc.php

// Create a product without a manufacturer to trigger the bug
$product = new Product();
$product->price = 10.00;
$product->id_manufacturer = 0; // No manufacturer
$product->id_category_default = 2;
$product->name = [1 => 'Bug Test Product'];
$product->link_rewrite = [1 => 'bug-test-product'];
$product->active = 1;
$product->add();

// We need a custom error handler to catch the Warning as an Exception
set_error_handler(function ($errno, $errstr) {
    if (strpos($errstr, 'Trying to access array offset on value of type null') !== false) {
        throw new Exception($errstr);
    }
    return false;
}, E_WARNING);

try {
    // Instantiate the controller
    $controller = new ProductController();
    
    // Use Reflection to set protected properties
    $ref = new ReflectionClass($controller);
    
    // Set context
    $propContext = $ref->getProperty('context');
    $propContext->setAccessible(true);
    $propContext->setValue($controller, $context);
    
    // Set product
    $propProduct = $ref->getProperty('product');
    $propProduct->setAccessible(true);
    $propProduct->setValue($controller, $product);
    
    // Set errors to empty to enter the logic block in initContent
    $propErrors = $ref->getProperty('errors');
    $propErrors->setAccessible(true);
    $propErrors->setValue($controller, []);

    echo "Testing initContent with product id: " . $product->id . " (id_manufacturer = 0)\n";
    
    // This method contains the buggy code
    $controller->initContent();
    
    echo "No warning detected. The bug is fixed.\n";
    exit(0);
} catch (Exception $e) {
    echo "Caught expected warning: " . $e->getMessage() . "\n";
    exit(1);
} catch (\Throwable $t) {
    echo "Unexpected error: " . $t->getMessage() . "\n";
    exit(1);
} finally {
    restore_error_handler();
}
