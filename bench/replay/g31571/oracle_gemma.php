<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #31571, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context to avoid null pointer exceptions in parent::init()
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);
Context::getContext()->currency = new Currency(1);

// Simulate the problematic URL: ?add=1&id_product=41261&qty=
// Tools::getValue('qty', 1) will return '' (empty string) because the key exists but is empty.
$_GET = [
    'add' => '1',
    'id_product' => '41261',
    'qty' => '', 
];

// Use output buffering to prevent "headers already sent" warnings 
// caused by header() calls inside CartController::init()
ob_start();

try {
    $controller = new CartController();
    
    // The bug is triggered during the assignment: $this->qty = abs(Tools::getValue('qty', 1));
    // In PHP 8, abs('') throws a TypeError.
    $controller->init();
    
    ob_end_clean();
    echo "Success: CartController::init() executed without TypeError.\n";
    exit(0);
} catch (\Throwable $t) {
    ob_end_clean();
    echo "Caught error: " . get_class($t) . " - " . $t->getMessage() . "\n";
    
    // If it's a TypeError and mentions abs(), the bug is still present
    if ($t instanceof \TypeError && strpos($t->getMessage(), 'abs()') !== false) {
        echo "Bug reproduced: abs() received a string instead of int|float.\n";
        exit(1);
    }
    
    // Other errors are treated as failures
    echo "Unexpected error occurred.\n";
    exit(1);
}
