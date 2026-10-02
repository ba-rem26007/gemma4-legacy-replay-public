<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #34523, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);
$context->cart = new Cart();
$context->cart->id_currency = 1;
$context->cart->id_lang = 1;
$context->cart->add();

// Ensure Product 1 is active, visible, and has the required values in both tables
// We use DB directly to bypass ObjectModel validation and ensure consistency
Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'product SET active = 1, visibility = "both", price = 10.0, unit_price = 0.0, unity = "kg" WHERE id_product = 1');
Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'product_shop SET active = 1, visibility = "both", price = 10.0, unit_price = 0.0, unity = "kg" WHERE id_product = 1 AND id_shop = 1');

// Prepare Product object to use its helper method for combinations
$p = new Product(1);

// Create a combination with a unit_price_impact > 0.
// Signature: addCombinationEntity($wholesale_price, $price, $weight, $unit_impact, $ecotax, $quantity, $id_images, $reference, $id_supplier, $ean13, $default, ...)
$id_combination = $p->addCombinationEntity(
    0,      // wholesale_price
    0,      // price impact
    0,      // weight
    2.0,    // unit_price_impact (The key for the bug: base unit_price is 0, so this must be added)
    0,      // ecotax
    10,     // quantity
    '',     // id_images
    '',     // reference
    0,      // id_supplier
    '',     // ean13
    0       // default
);

// Clear cache to ensure we get fresh data from DB
Product::resetStaticCache();

// Trigger the logic via getProductProperties, which calls computeUnitPriceRatio
// Signature: getProductProperties($id_product, $id_product_attribute = null, ?Context $context = null)
$properties = Product::getProductProperties(1, (int)$id_combination, $context);

if (!is_array($properties)) {
    echo "FAILURE: getProductProperties did not return an array. Product might be inactive or not found.\n";
    exit(1);
}

$unitPriceTaxExcl = (float)$properties['unit_price_tax_excluded'];

echo "Product Price: 10\n";
echo "Product Base Unit Price: 0\n";
echo "Combination Unit Price Impact: 2.0\n";
echo "Observed unit_price_tax_excluded: $unitPriceTaxExcl\n";

/**
 * Logic:
 * Ratio = baseUnitPrice + unit_price_impact
 * Before fix: if (0 != impact && 0 != baseUnitPrice) -> 0 != 2 && 0 != 0 is FALSE. Ratio = 0.
 * After fix: Ratio = 0 + 2 = 2.
 * 
 * Final Unit Price = Price / Ratio
 * Before fix: Ratio is 0 -> unit_price_tax_excluded = 0.
 * After fix: 10 / 2 = 5.
 */

if ($unitPriceTaxExcl > 0) {
    echo "SUCCESS: Unit price is correctly computed.\n";
    exit(0);
} else {
    echo "FAILURE: Unit price is 0, the bug is still present.\n";
    exit(1);
}
