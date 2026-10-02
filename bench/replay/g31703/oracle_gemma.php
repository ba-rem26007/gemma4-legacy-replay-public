<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #31703, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->employee = new Employee(1);

// Simulate the request for a virtual URL with special characters
// The bug is that these characters were previously accepted without error
$_POST['virtual_uri'] = 'é_special_char';
$_POST['domain'] = 'example.com';
$_POST['domain_ssl'] = 'example.com';
$_POST['physical_uri'] = '/';

try {
    // Instantiate the controller directly
    $controller = new AdminShopUrlController();
    
    // We call processSave directly to trigger the validation logic
    // processSave calls loadObject(true) which creates a new ShopUrl object
    $controller->processSave();

    $errors = $controller->errors;
    $errorCount = count($errors);
    
    echo "Virtual URI tested: " . $_POST['virtual_uri'] . "\n";
    echo "Number of errors found: $errorCount\n";
    foreach ($errors as $error) {
        echo "Error message: $error\n";
    }

    // The fix adds an error if the virtual_uri contains special characters.
    // If the fix is applied, $errorCount should be > 0.
    // If the fix is NOT applied, $errorCount will be 0.
    exit($errorCount > 0 ? 0 : 1);

} catch (\Throwable $t) {
    echo "Exception caught: " . $t->getMessage() . "\n";
    exit(1);
}
