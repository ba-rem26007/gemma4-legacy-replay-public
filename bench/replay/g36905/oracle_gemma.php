<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #36905, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

/**
 * We need to call the protected method _deleteCustomization.
 * We create a wrapper class to make it public for the test.
 */
class CartTest extends Cart {
    public function callDeleteCustomization($id_customization, $id_product, $id_product_attribute) {
        return $this->_deleteCustomization((int)$id_customization, (int)$id_product, (int)$id_product_attribute);
    }
}

$id_product = 1;
$id_product_attribute = 0;

// Create a Cart
$cart = new Cart();
$cart->id_currency = 1;
$cart->id_lang = 1;
$cart->id_customer = 1; // Use demo customer
$cart->add();
$id_cart = (int)$cart->id;

// 1. Create the customization record in ps_customization
// This is required because _deleteCustomization checks if the record exists first
Db::getInstance()->insert('customization', [
    'id_cart' => $id_cart,
    'id_product' => $id_product,
    'id_product_attribute' => $id_product_attribute,
    'quantity' => 1,
    'in_cart' => 1,
    'id_address_delivery' => 0
]);
$id_customization = (int)Db::getInstance()->Insert_ID();

// 2. Create multiple file-type customization data
$files = ['bug_test_1.jpg', 'bug_test_2.jpg', 'bug_test_3.jpg'];
foreach ($files as $index => $filename) {
    Db::getInstance()->insert('customized_data', [
        'id_customization' => $id_customization,
        'type' => (int)Product::CUSTOMIZE_FILE,
        'index' => (int)$index,
        'value' => $filename,
        'id_module' => 0,
        'price' => 0,
        'weight' => 0
    ]);
    
    // Create physical files in the upload directory
    touch(_PS_UPLOAD_DIR_ . $filename);
    touch(_PS_UPLOAD_DIR_ . $filename . '_small');
}

echo "Setup: Customization $id_customization created with " . count($files) . " files.\n";

try {
    // Use the wrapper to call the protected method directly
    $cartTest = new CartTest();
    $cartTest->id = $id_cart;
    $cartTest->callDeleteCustomization($id_customization, $id_product, $id_product_attribute);
} catch (\Throwable $e) {
    echo "Error during execution: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Verify if ALL files were deleted
$remaining_files = [];
foreach ($files as $filename) {
    if (file_exists(_PS_UPLOAD_DIR_ . $filename)) {
        $remaining_files[] = $filename;
    }
    if (file_exists(_PS_UPLOAD_DIR_ . $filename . '_small')) {
        $remaining_files[] = $filename . '_small';
    }
}

if (count($remaining_files) > 0) {
    echo "Failure: " . count($remaining_files) . " files still exist: " . implode(', ', $remaining_files) . "\n";
    exit(1);
}

echo "Success: All customization files were physically deleted.\n";
exit(0);
