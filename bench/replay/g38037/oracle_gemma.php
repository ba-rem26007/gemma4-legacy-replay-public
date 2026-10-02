<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #38037, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

$id_product = 1;
$query = 'BugTestProduct';

// Ensure product has a searchable name and is initialized
$p_init = new Product($id_product);
$p_init->name = [1 => $query];
$p_init->price = 10;
$p_init->save();

/**
 * Setup the bug condition:
 * We need ps_product.active = 0 AND ps_product_shop (shop 1).active = 1.
 * 
 * In PrestaShop, Product::save() updates both the global table (ps_product) 
 * and the shop table (ps_product_shop). The last save determines the global value.
 */

// 1. Set active in Shop 1
// This sets ps_product.active = 1 AND ps_product_shop(1).active = 1
Shop::setContext(Shop::CONTEXT_SHOP, 1);
$p1 = new Product($id_product);
$p1->active = 1;
$p1->save();

// 2. Set inactive in Shop 2
// This sets ps_product.active = 0 AND ps_product_shop(2).active = 0
// Crucially, ps_product_shop(1).active remains 1.
Shop::setContext(Shop::CONTEXT_SHOP, 2);
$p2 = new Product($id_product);
$p2->active = 0;
$p2->save();

// Switch back to Shop 1 context for the search
Shop::setContext(Shop::CONTEXT_SHOP, 1);

// Verification of setup: ps_product.active must be 0
$p_verify = new Product($id_product);
$global_active = (int)$p_verify->active; 
echo "Global ps_product.active: $global_active\n";

if ($global_active !== 0) {
    echo "Setup failed: ps_product.active should be 0 to trigger the bug\n";
    exit(1);
}

// Perform search in Shop 1 context
// The result should use product_shop.active (which is 1 for Shop 1)
$results = Product::searchByName(1, $query);

if (empty($results)) {
    echo "Product not found in search results\n";
    exit(1);
}

$observedActive = (int)$results[0]['active'];
echo "Observed active value in search results for Shop 1: $observedActive\n";

// If bug exists: returns p.active (0)
// If fixed: returns product_shop.active (1)
if ($observedActive === 1) {
    echo "SUCCESS: Product is active in the contextual shop.\n";
    exit(0);
} else {
    echo "FAILURE: Product is inactive (bug: took value from ps_product).\n";
    exit(1);
}
