<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33151, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

// Fix the "isLoggedBack() on null" error by providing an employee
$employee = new Employee(1);
if (!Validate::isLoadedObject($employee)) {
    $employee = new Employee();
    $employee->firstname = 'Test';
    $employee->lastname = 'User';
    $employee->email = 'test@example.com';
    $employee->passwd = 'password';
    $employee->add();
}
$context->employee = $employee;

// Enable Multi-store feature
Configuration::updateValue('PS_MULTISHOP_FEATURE_ENABLED', 1);
// Set context to ALL to trigger the logic in processUpdate: Shop::getContext() != Shop::CONTEXT_SHOP
Shop::setContext(Shop::CONTEXT_ALL);

// Use existing product 1
$id_product = 1;
$product = new Product($id_product);
if (!Validate::isLoadedObject($product)) {
    $product = new Product();
    $product->price = 10.0;
    $product->add();
    $id_product = $product->id;
}

$old_ref = $product->reference;
$new_ref = 'REF_UPDATED_' . time();

echo "Initial reference: $old_ref\n";
echo "Target reference: $new_ref\n";

// Simulate the POST request for AdminProductsController::processUpdate
// We must provide 'id_product' for loadObject()
$_POST['id_product'] = $id_product;
$_POST['reference'] = $new_ref;

// In multi-store "All Shops" context, the admin selects which shops to update.
// The fix ensures that 'reference' is added to the fields to update regardless of 
// whether it was explicitly checked in the multishop_check list, as long as 
// the context is not a single shop.
$_POST['multishop_check'] = [1 => 1]; 

try {
    // Instantiate the controller directly
    $controller = new AdminProductsController();
    
    // Call the method that handles the update
    // This method calls copyFromPost() and then the logic in the diff, then update()
    $controller->processUpdate();
} catch (\Throwable $e) {
    echo "Error during processUpdate: " . $e->getMessage() . "\n";
    exit(1);
}

// Reload product from DB to check if the reference was actually updated
$product_updated = new Product($id_product);
$observed_ref = $product_updated->reference;

echo "Observed reference after update: $observed_ref\n";

if ($observed_ref === $new_ref) {
    echo "SUCCESS: Product reference was updated.\n";
    exit(0);
} else {
    echo "FAILURE: Product reference remained $observed_ref\n";
    exit(1);
}
