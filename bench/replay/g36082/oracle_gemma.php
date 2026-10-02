<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #36082, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Test for fix: count(): Argument #1 ($value) must be of type Countable
 * The bug occurs in RequestSql::returnNameTable when $alias is false, 
 * multiple tables are provided, and an attribute is found.
 * In PHP 8, assigning a string to $tab and then calling count($tab) throws a TypeError.
 */

try {
    // RequestSql requires a SQL string in its constructor
    $requestSql = new RequestSql("SELECT * FROM ps_product");

    // To trigger the bug, we need:
    // 1. $alias = false
    // 2. count($tables) > 1
    // 3. $attr = a column that exists in one of the tables
    $tables = [
        ['table' => 'ps_product'],
        ['table' => 'ps_stock_available'],
    ];
    
    // 'reference' exists in ps_product but not in ps_stock_available
    $attr = 'reference';

    echo "Testing returnNameTable with alias=false, multiple tables, and attribute '$attr'...\n";
    
    // This call triggers the loop where $tab is assigned
    $result = $requestSql->returnNameTable(false, $tables, $attr);

    echo "Result: ";
    var_dump($result);
    echo "\n";

    // If we reached here without a TypeError, the fix is working
    exit(0);
} catch (\Throwable $t) {
    echo "Caught exception: " . get_class($t) . " - " . $t->getMessage() . "\n";
    
    // If the error is the specific PHP 8 count() TypeError, the bug is still present
    if (strpos($t->getMessage(), 'count(): Argument #1') !== false) {
        echo "Bug reproduced: count() called on a non-countable value.\n";
        exit(1);
    }
    
    // Any other error is a test failure
    exit(1);
}
