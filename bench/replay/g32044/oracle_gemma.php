<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #32044, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

try {
    echo "Testing Product::add() with empty string for unit_price...\n";
    
    $product = new Product();
    // Required field
    $product->price = 10.0;
    
    // Trigger: empty string instead of null or number
    // Before fix: ($this->unit_price ?? 0) where unit_price is "" results in ""
    // After fix: ($this->unit_price ?: 0) where unit_price is "" results in 0
    $product->unit_price = ""; 
    
    // This method calls updateUnitRatio() -> fillUnitRatio()
    $product->add();
    
    echo "Product added successfully without exception.\n";
    exit(0);
} catch (\Throwable $t) {
    echo "Caught expected or unexpected exception: " . get_class($t) . "\n";
    echo "Message: " . $t->getMessage() . "\n";
    
    // The bug is specifically an InvalidArgumentException from the Decimal library
    if ($t instanceof \InvalidArgumentException && strpos($t->getMessage(), 'cannot be interpreted as a number') !== false) {
        echo "Bug reproduced: InvalidArgumentException thrown due to empty string in DecimalNumber.\n";
        exit(1);
    }
    
    echo "An unrelated error occurred.\n";
    exit(1);
}
