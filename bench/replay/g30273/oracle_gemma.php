<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #30273, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Test for Ticket: Error in cart - removing products in debug mode and specific language doesn't work
 * The bug is caused by a PHP Notice when 'id_manufacturer' is missing from the product row array
 * in Product::getProductProperties(). In debug mode, this notice corrupts AJAX responses.
 */

$context = Context::getContext();
$context->shop = new Shop(1);
$context->link = new Link();
$context->language = new Language(1);
$context->currency = new Currency(1);

$id_lang = 1;
$id_product = 1;

// We fetch a real product to get most of the required fields.
$row = Db::getInstance()->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'product WHERE id_product = ' . (int)$id_product);

if (!$row) {
    echo "Error: Demo product 1 not found in database.\n";
    exit(1);
}

// Manually fill keys that are typically provided by the query in Product::getProducts 
// but are missing from a simple SELECT * FROM ps_product.
$row['link_rewrite'] = 'product-1';
$row['out_of_stock'] = 2; 
$row['id_category_default'] = 2;

// Trigger the bug: remove the key that the old code accesses without checking existence.
unset($row['id_manufacturer']);

// We use a custom error handler to catch ONLY the notice related to 'id_manufacturer'.
// Other notices (like the ProductSettings constant) are ignored to avoid false positives.
set_error_handler(function($errno, $errstr) {
    if (strpos($errstr, 'id_manufacturer') !== false) {
        throw new Exception($errstr);
    }
    return false; // Let other errors be handled normally or ignored
});

try {
    // Call the method touched by the fix.
    // The fix changes the check to !empty($row['id_manufacturer']), which is safe.
    Product::getProductProperties($id_lang, $row, $context);
    
    restore_error_handler();
    echo "Success: No notice triggered for missing id_manufacturer. The bug is fixed.\n";
    exit(0);
} catch (\Throwable $t) {
    restore_error_handler();
    echo "Failure: Notice triggered: " . $t->getMessage() . "\n";
    exit(1);
}
