<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #36834, validé pre/post automatiquement
require 'config/config.inc.php';

// Context setup
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);
Context::getContext()->currency = new Currency(1);

// Demo data
$id_product = 1;
$id_product_attribute = 0;
$id_address_delivery = 0;
$id_customization = 100;
$product_price = 10.00;
$customization_price = 5.00;
$quantity = 1;

// Ensure product exists
$product = new Product($id_product);
if (!Validate::isLoadedObject($product)) {
    $product = new Product();
    $product->id = $id_product;
    $product->price = $product_price;
    $product->id_tax_rules_group = 0; 
    $product->add();
}

/**
 * The bug is in Product::addCustomizationPrice.
 * 
 * Logic:
 * 1. It starts with $price = $product_update['unit_price_tax_excl'] (10.00).
 * 2. It iterates through customized_datas and adds the price of each customization to $price.
 *    $price = 10.00 + 5.00 = 15.00.
 * 3. Buggy version: $product_update['total_customization'] = $price * $customization_quantity;
 *    Result: 15.00 * 1 = 15.00.
 * 4. Fixed version: $product_update['total_customization'] = $product_update['unit_price_tax_excl'] * $customization_quantity;
 *    Result: 10.00 * 1 = 10.00.
 */
$products = [
    [
        'id_product' => $id_product,
        'id_product_attribute' => $id_product_attribute,
        'id_address_delivery' => $id_address_delivery,
        'product_quantity' => $quantity,
        'product_price' => $product_price,
        'rate' => 0,
        'unit_price_tax_excl' => $product_price,
        'unit_price_tax_incl' => $product_price,
        'quantity' => $quantity,
        'total' => $product_price * $quantity,
        'total_wt' => $product_price * $quantity,
    ]
];

// We must provide the exact structure that Product::addCustomizationPrice expects to avoid warnings
// and to ensure the customization price is actually added to the internal $price variable.
$customized_datas = [
    (int)$id_product => [
        (int)$id_product_attribute => [
            (int)$id_address_delivery => [
                (int)$id_customization => [
                    'id_customization' => $id_customization,
                    'quantity' => $quantity,
                    'quantity_refunded' => 0,
                    'quantity_returned' => 0,
                    'datas' => [
                        1 => [ // type 1: text
                            [
                                'id_customization' => $id_customization,
                                'price' => $customization_price,
                                'value' => 'Custom Value'
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ]
];

try {
    // Call the method touched by the fix
    Product::addCustomizationPrice($products, $customized_datas);

    $observed_total_custom = $products[0]['total_customization'];

    echo "Product Base Price: $product_price\n";
    echo "Customization Price: $customization_price\n";
    echo "Observed total_customization: $observed_total_custom\n";

    // After fix, total_customization should only be the base product price * qty
    $expected_fixed = $product_price * $quantity; // 10.00
    // Before fix, it was (base + custom) * qty
    $expected_buggy = ($product_price + $customization_price) * $quantity; // 15.00

    if (abs($observed_total_custom - $expected_fixed) < 0.0001) {
        echo "Test PASSED: total_customization is the base price.\n";
        exit(0);
    } else {
        echo "Test FAILED: total_customization is $observed_total_custom, expected $expected_fixed\n";
        if (abs($observed_total_custom - $expected_buggy) < 0.0001) {
            echo "Bug detected: total_customization includes the customization price.\n";
        }
        exit(1);
    }
} catch (\Throwable $t) {
    echo "Exception: " . $t->getMessage() . "\n";
    exit(1);
}
