<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #30465, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

// Create a Cart (required fields: id_currency, id_lang)
$cart = new Cart();
$cart->id_currency = 1;
$cart->id_lang = 1;
$cart->add();

$id_product = 1;
$index = 1;

// We want to trigger the bug: call deleteCustomizationToProduct for a product 
// that has NO customization associated with this cart.
// In the old code, $cust_data will be false, and accessing $cust_data['id_customization'] 
// will trigger a PHP Warning/Error.

// Convert warnings to exceptions to detect the bug
set_error_handler(function($errno, $errstr) {
    throw new Exception($errstr);
});

try {
    echo "Testing deleteCustomizationToProduct with non-existent customization...\n";
    
    // This method should return true immediately if no customization is found (after fix)
    // Before fix, it attempts to access array offsets on a boolean (false), triggering a warning.
    $result = $cart->deleteCustomizationToProduct($id_product, $index);
    
    echo "Result: " . ($result ? 'true' : 'false') . "\n";
    restore_error_handler();
    exit(0); // Success: no error triggered
} catch (\Throwable $t) {
    restore_error_handler();
    echo "Caught expected bug: " . $t->getMessage() . "\n";
    exit(1); // Failure: bug is present
}
