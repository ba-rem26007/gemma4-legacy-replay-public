<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33113, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->link = new Link();

/**
 * The bug is a PHP Notice/Warning "Undefined array key" triggered in Product::getProductProperties
 * when $row['id_manufacturer'] is accessed without an isset() check.
 * 
 * To reach this line without triggering other "Undefined index" errors (like 'out_of_stock'),
 * we must provide a $row array containing all keys accessed by the method prior to the bug.
 */

set_error_handler(function($errno, $errstr) {
    throw new \ErrorException($errstr, 0, $errno);
});

// We populate the array with all keys typically expected by getProductProperties
// based on the SQL query provided in the ticket, EXCEPT 'id_manufacturer'.
$row = [
    'id_product' => 1,
    'id_category_default' => 2,
    'link_rewrite' => 'product-1',
    'ean13' => '123456789',
    'out_of_stock' => 0,
    'description' => 'desc',
    'description_short' => 'short',
    'meta_description' => 'meta',
    'meta_keywords' => 'keys',
    'meta_title' => 'title',
    'name' => 'name',
    'available_now' => 'now',
    'available_later' => 'later',
    'isbn' => 'isbn',
    'upc' => 'upc',
    'mpn' => 'mpn',
    'id_image' => 1,
    'legend' => 'legend',
    'id_shop' => 1,
    'id_lang' => 1,
    // 'id_manufacturer' is intentionally missing to trigger the bug
];

try {
    // Call the method touched by the fix
    Product::getProductProperties(1, $row, $context);
} catch (\ErrorException $e) {
    restore_error_handler();
    if (strpos($e->getMessage(), 'id_manufacturer') !== false) {
        echo "Bug reproduced: Caught expected error: " . $e->getMessage() . "\n";
        exit(1); // Fail: the bug is still present
    } else {
        echo "Caught an unrelated error: " . $e->getMessage() . "\n";
        // If we hit another missing key, it means our $row is still incomplete.
        // In a real regression test, this should be considered a failure of the test setup.
        exit(1); 
    }
} catch (\Throwable $t) {
    restore_error_handler();
    echo "Unexpected exception: " . $t->getMessage() . "\n";
    exit(1);
}

restore_error_handler();

echo "Success: No 'id_manufacturer' notice triggered. The fix is working.\n";
exit(0); // Pass: the fix (isset check) is working
