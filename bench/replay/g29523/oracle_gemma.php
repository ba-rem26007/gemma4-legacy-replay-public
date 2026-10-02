<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29523, validé pre/post automatiquement
require 'config/config.inc.php';

// Context setup
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

try {
    $id_product = 1;
    $product = new Product($id_product);

    // Ensure we have a combination to test with
    $id_pa = (int) Product::getDefaultAttribute($id_product);
    if ($id_pa === 0) {
        // Signature: addProductAttribute($price, $weight, $unit_impact, $ecotax, $quantity, $id_images, $reference, $id_supplier, $ean13, $default, $location, $upc, $minimal_quantity, $isbn, $low_stock_threshold = null, $low_stock_alert = false, $mpn = null)
        $id_pa = $product->addProductAttribute(
            0, 0, 0, 0, 10, [], 'REF_START', 0, 'EAN_START', 0, '', 'UPC_START', 1, 'ISBN_START'
        );
    }

    echo "Testing combination ID: $id_pa\n";

    // Values to update - constrained to DB lengths
    $new_ref = 'REF_UPD_123';
    $new_ean = '1234567890123'; // 13 chars
    $new_upc = '123456789012';  // 12 chars
    $new_isbn = 'ISBN_UPD_123';
    $new_mpn = 'MPN_UPD_123';
    $new_threshold = 15;
    $new_alert = true;

    /**
     * We use updateProductAttribute because its signature is explicitly provided 
     * in the "CODE AVANT CORRECTIF" section and it calls updateAttribute() internally.
     * 
     * Signature: updateProductAttribute(
     *   $id_product_attribute, $wholesale_price, $price, $weight, $unit, $ecotax, 
     *   $id_images, $reference, $id_supplier, $ean13, $default, $location, 
     *   $upc, $minimal_quantity, $available_date, $isbn = '', 
     *   $low_stock_threshold = null, $low_stock_alert = false, $mpn = null
     * )
     */
    $product->updateProductAttribute(
        $id_pa,          // 1
        0,              // 2: wholesale_price
        0,              // 3: price
        0,              // 4: weight
        0,              // 5: unit
        0,              // 6: ecotax
        [],             // 7: id_images
        $new_ref,       // 8: reference
        0,              // 9: id_supplier
        $new_ean,       // 10: ean13
        0,              // 11: default
        '',             // 12: location
        $new_upc,       // 13: upc
        1,              // 14: minimal_quantity
        '0000-00-00',   // 15: available_date
        $new_isbn,      // 16: isbn
        $new_threshold, // 17: low_stock_threshold
        $new_alert,     // 18: low_stock_alert
        $new_mpn        // 19: mpn
    );

    // Verify the data was actually saved in the database
    $combination = new Combination($id_pa);
    
    echo "Observed Reference: " . $combination->reference . " (Expected: $new_ref)\n";
    echo "Observed EAN13: " . $combination->ean13 . " (Expected: $new_ean)\n";
    echo "Observed UPC: " . $combination->upc . " (Expected: $new_upc)\n";
    echo "Observed ISBN: " . $combination->isbn . " (Expected: $new_isbn)\n";
    echo "Observed MPN: " . $combination->mpn . " (Expected: $new_mpn)\n";
    echo "Observed Threshold: " . $combination->low_stock_threshold . " (Expected: $new_threshold)\n";
    echo "Observed Alert: " . ($combination->low_stock_alert ? '1' : '0') . " (Expected: 1)\n";

    $success = (
        $combination->reference === $new_ref &&
        $combination->ean13 === $new_ean &&
        $combination->upc === $new_upc &&
        $combination->isbn === $new_isbn &&
        $combination->mpn === $new_mpn &&
        (int)$combination->low_stock_threshold === $new_threshold &&
        (int)$combination->low_stock_alert === 1
    );

    exit($success ? 0 : 1);

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
