<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33964, validé pre/post automatiquement
require 'config/config.inc.php';

try {
    // Setup context
    Context::getContext()->shop = new Shop(1);
    Context::getContext()->language = new Language(1);
    Context::getContext()->currency = new Currency(1);

    /**
     * To expose the bug, we must ensure that Product::getPrice() returns a value 
     * with more precision than the computing precision used for rounding.
     * 
     * Product::getPrice() rounds the result based on the Currency precision.
     * The fix rounds the result based on the Computing Precision (PS_PRICE_COMPUTING_PRECISION).
     */

    // 1. Set Currency precision to 6 (maximum) so getPrice() returns high precision
    Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'currency` SET `precision` = 6 WHERE `id_currency` = 1');
    
    // 2. Set Computing precision to 2 (standard)
    Configuration::updateValue('PS_PRICE_COMPUTING_PRECISION', 2);
    
    // 3. Set rounding type to ROUND_ITEM (Value 2)
    // This is the mode where the unit price MUST be rounded before multiplying by quantity.
    Configuration::updateValue('PS_ROUND_TYPE', Order::ROUND_ITEM);

    // Product 1: Price 22.944. 
    // With currency precision 6, getPrice() will return exactly 22.944.
    $p1 = new Product(1);
    $p1->price = 22.944;
    $p1->id_tax_rules_group = 0; // No tax to avoid further rounding/calculations
    $p1->save();

    // Product 19: The Pack
    $pPack = new Product(19);
    $pPack->price = 0;
    $pPack->save();

    // Clear pack cache and add items
    Pack::resetStaticCache();
    Pack::deleteItems(19);
    Pack::addItem(19, 1, 10, 0); // 10 units of Product 1

    /**
     * Calculation Logic:
     * 
     * Unit Price = 22.944
     * Quantity = 10
     * 
     * BUGGY (Before fix):
     * sum = 22.944 * 10 = 229.44
     * 
     * FIXED (After fix):
     * precision = 2 (from PS_PRICE_COMPUTING_PRECISION)
     * roundedUnitPrice = Tools::ps_round(22.944, 2) = 22.94
     * sum = 22.94 * 10 = 229.40
     */

    $observedSum = Pack::noPackPrice(19);
    $expectedSum = 229.40;

    echo "Currency Precision: 6\n";
    echo "Computing Precision: 2\n";
    echo "Rounding Type: " . Configuration::get('PS_ROUND_TYPE') . " (ROUND_ITEM)\n";
    echo "Observed Sum: $observedSum\n";
    echo "Expected Sum: $expectedSum\n";

    // We check for a strict match to 2 decimals.
    // If the bug is present, $observedSum will be 229.44.
    if (abs($observedSum - $expectedSum) < 0.0001) {
        exit(0);
    } else {
        echo "Bug detected: The sum is not rounded per item. Observed $observedSum instead of $expectedSum\n";
        exit(1);
    }
} catch (\Throwable $t) {
    echo "Fatal Error: " . $t->getMessage() . "\n";
    exit(1);
}
