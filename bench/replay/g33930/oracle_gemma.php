<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33930, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);
Context::getContext()->currency = new Currency(1);

try {
    // 1. Setup Product
    $p = new Product(1);
    $p->price = 10.0; // Base price in EUR
    $p->update();

    // 2. Setup Currency (CZK)
    $c2 = new Currency();
    $c2->iso_code = 'CZK';
    $c2->numeric_iso_code = '208';
    $c2->conversion_rate = 25.0; // 1 EUR = 25 CZK
    $c2->add();
    $id_currency_czk = (int)$c2->id;

    // 3. Setup Specific Price for CZK
    // We want a fixed price of 200.0 CZK for this product.
    $sp = new SpecificPrice();
    $sp->id_product = 1;
    $sp->id_shop = 1;
    $sp->id_shop_group = 1;
    $sp->id_currency = $id_currency_czk;
    $sp->id_country = 1;
    $sp->id_group = 1;
    $sp->id_customer = 0;
    $sp->id_product_attribute = 0;
    $sp->price = 200.0; // This should be the final price in CZK
    $sp->from_quantity = 1;
    $sp->reduction = 0;
    $sp->reduction_tax = 1;
    $sp->reduction_type = 'amount';
    $sp->from = '2020-01-01 00:00:00';
    $sp->to = '2030-01-01 00:00:00';
    $sp->add();

    Product::resetStaticCache();

    // 4. Execute price calculation for the target currency (CZK)
    // Signature: priceCalculation($id_shop, $id_product, $id_product_attribute, $id_country, $id_state, $zipcode, $id_currency, $id_group, $quantity, $usetax, $decimals, $id_customer = null, $id_cart = null, $id_address = null, &$specific_price = null, $use_tax_excl = true)
    $specific_price_out = null;
    $calculatedPrice = Product::priceCalculation(
        1,              // id_shop
        1,              // id_product
        0,              // id_product_attribute
        1,              // id_country
        0,              // id_state
        '00000',        // zipcode
        $id_currency_czk, // id_currency
        1,              // id_group
        1,              // quantity
        false,          // usetax
        2,              // decimals
        0,              // id_customer
        0,              // id_cart
        0,              // id_address
        $specific_price_out, // &$specific_price
        true            // use_tax_excl
    );

    echo "Base Price: 10.0 EUR\n";
    echo "Target Currency Rate: 25.0\n";
    echo "Specific Price set for CZK: 200.0\n";
    echo "Calculated Price: $calculatedPrice\n";

    // Before fix: The code converts the specific price (200 * 25 = 5000)
    // After fix: The code detects the currency match and keeps 200.0
    if (abs($calculatedPrice - 200.0) < 0.0001) {
        exit(0);
    } else {
        echo "Error: Price was converted incorrectly. Expected 200.0, got $calculatedPrice\n";
        exit(1);
    }

} catch (\Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    exit(1);
}
