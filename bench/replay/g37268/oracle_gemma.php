<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37268, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);
Context::getContext()->currency = new Currency(1);

try {
    // 1. Setup Currencies
    // Default currency (1)
    $currencyDefault = new Currency(1);
    $currencyDefault->conversion_rate = 1.0;
    $currencyDefault->save();

    // Second currency (2) with a conversion rate of 0.5
    // We use 0.5 to make the bug very obvious (8.0 becomes 4.0)
    $currencyEur = new Currency(2);
    $currencyEur->iso_code = 'EUR';
    $currencyEur->conversion_rate = 0.5;
    $currencyEur->save();

    // Clear PrestaShop's internal currency cache to ensure the 0.5 rate is used
    Currency::flush();

    // 2. Setup Product
    $product = new Product(1);
    $product->price = 100.0;
    $product->save();

    // 3. Setup Specific Price for the second currency
    // We set a manual price of 8.0 for currency 2.
    $sp = new SpecificPrice();
    $sp->id_product = 1;
    $sp->id_currency = 2; 
    $sp->price = 8.0;     
    $sp->from_quantity = 1;
    $sp->reduction = 0;
    $sp->reduction_type = 'amount';
    $sp->from = '2000-01-01 00:00:00';
    $sp->to = '2099-12-31 23:59:59';
    $sp->id_shop = 1;
    $sp->id_shop_group = 1;
    $sp->id_country = 1;
    $sp->id_group = 1;
    $sp->id_customer = 0;
    $sp->id_product_attribute = 0;
    $sp->id_specific_price_rule = 0;
    $sp->id_cart = 0;
    $sp->add();

    // 4. Execute priceCalculation
    // To trigger the bug, we need a type mismatch between the input $id_currency 
    // and the value returned from the database for the specific price.
    // In many environments, DB returns strings. Passing an integer here triggers '2' === 2 (false).
    $id_currency_int = 2;
    
    $sp_ref = null;
    $calculatedPrice = Product::priceCalculation(
        1,              // id_shop
        1,              // id_product
        0,              // id_product_attribute
        1,              // id_country
        0,              // id_state
        '',             // zipcode
        $id_currency_int, // id_currency (passed as int)
        1,              // id_group
        1,              // quantity
        false,          // usetax
        2,              // decimals
        0,              // id_customer
        0,              // id_cart
        0,              // id_address
        $sp_ref,        // specific_price (passed by reference)
        true,           // use_reduc
        true            // with_eco_tax
    );

    echo "Expected price: 8.0\n";
    echo "Observed price: $calculatedPrice\n";

    // If the bug is present:
    // The strict check ($id_currency === $specific_price['id_currency']) fails.
    // The code then runs Tools::convertPrice(8.0, 2), which is 8.0 * 0.5 = 4.0.
    
    // If the bug is fixed:
    // The check (int)2 === (int)'2' is true.
    // The conversion is skipped, and the price remains 8.0.

    if (abs((float)$calculatedPrice - 8.0) < 0.0001) {
        // Correct behavior: specific price is kept
        exit(0);
    } else {
        // Buggy behavior: specific price was converted
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
