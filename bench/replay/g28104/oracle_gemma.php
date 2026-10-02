<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #28104, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

try {
    // 1. Prepare data: Product 1 must be in both Category 2 and Category 3
    // to trigger duplicate results in getAffectedProducts()
    $product = new Product(1);
    if (!Validate::isLoadedObject($product)) {
        $product = new Product();
        $product->price = 10.0;
        $product->id_category_default = 2;
        $product->add();
    }

    // Clear existing category associations for product 1 and add both
    Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'category_product WHERE id_product = ' . (int)$product->id);
    Db::getInstance()->insert('category_product', ['id_category' => 2, 'id_product' => (int)$product->id]);
    Db::getInstance()->insert('category_product', ['id_category' => 3, 'id_product' => (int)$product->id]);

    // 2. Create a SpecificPriceRule
    $rule = new SpecificPriceRule();
    $rule->name = 'Test Rule'; // Required field
    $rule->id_shop = 1;
    $rule->id_country = 1;
    $rule->id_currency = 1;
    $rule->id_group = 1;
    $rule->from_quantity = 1;
    $rule->price = 0; 
    $rule->reduction = 1.0;
    $rule->reduction_tax = 1;
    $rule->reduction_type = 'amount';
    $rule->add();

    // 3. Add two different condition groups that both match Product 1
    // addConditions() creates a new group each time it is called
    $rule->addConditions([['type' => 'category', 'value' => 2]]);
    $rule->addConditions([['type' => 'category', 'value' => 3]]);

    // 4. Enable rule application and trigger the bug
    SpecificPriceRule::enableAnyApplication();
    
    echo "Applying rule with overlapping conditions...\n";
    
    /**
     * BUG: In PHP 8, if getAffectedProducts returns duplicate products (because they match multiple groups),
     * apply() calls applyRuleToProduct() multiple times for the same product.
     * This leads to a SQL "Duplicate entry" exception on the ps_specific_price unique key.
     * The fix adds array_unique() to remove these duplicates.
     */
    $rule->apply();

    echo "Rule applied successfully without exception.\n";
    exit(0);

} catch (\Throwable $t) {
    echo "Caught exception: " . $t->getMessage() . "\n";
    // If we caught a SQL duplicate entry error, the bug is still present.
    exit(1);
}
