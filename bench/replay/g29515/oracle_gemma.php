<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29515, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

try {
    // 1. Use an existing product and disable it
    $idProduct = 1;
    $product = new Product($idProduct);
    if (!Validate::isLoadedObject($product)) {
        echo "Product 1 not found\n";
        exit(1);
    }
    $product->active = 0;
    $product->save();
    echo "Product $idProduct disabled.\n";

    // 2. Create a tag and associate it with the disabled product
    $tagName = 'RegressionTag_' . time();
    // Tag::addTags creates the tag in ps_tag and the association in ps_product_tag
    if (!Tag::addTags(1, $idProduct, $tagName)) {
        echo "Failed to add tag to product\n";
        exit(1);
    }

    // 3. Retrieve the tag object
    $idTag = (int)Db::getInstance()->getValue('
        SELECT id_tag FROM ' . _DB_PREFIX_ . 'tag 
        WHERE name = "' . pSQL($tagName) . '" AND id_lang = 1
    ');

    if (!$idTag) {
        echo "Tag not found in database\n";
        exit(1);
    }

    $tag = new Tag($idTag);
    echo "Tag created with ID: $idTag\n";

    // 4. Call the method touched by the fix: getProducts()
    // The bug: when $associated = true, it used to filter by product_shop.active = 1
    // This meant disabled products were not returned, even if they were associated.
    $associatedProducts = $tag->getProducts(true);

    $found = false;
    foreach ($associatedProducts as $row) {
        if ((int)$row['id_product'] === $idProduct) {
            $found = true;
            break;
        }
    }

    if ($found) {
        echo "Success: Disabled product $idProduct is correctly listed as associated with the tag.\n";
        exit(0);
    } else {
        echo "Failure: Disabled product $idProduct was not found in associated products list.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
