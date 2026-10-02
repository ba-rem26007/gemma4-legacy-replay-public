<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33387, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\Image\ImageRetriever;

// Setup context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

try {
    // 1. Prepare Product 1 with a specific name
    $product = new Product(1, false, 1);
    $product->name = 'Product Test Name';
    $product->save();

    // 2. Create a new image for this product without a legend
    // By not setting the legend, it will be empty in ps_image_lang
    $image = new Image();
    $image->id_product = 1;
    $image->position = 1;
    $image->cover = 0;
    $image->add();
    $id_image = $image->id;

    // 3. Instantiate the ImageRetriever
    $link = new Link();
    $retriever = new ImageRetriever($link);

    // 4. Call the method that was touched by the fix
    $productData = ['id_product' => 1];
    $language = new Language(1);
    $images = $retriever->getAllProductImages($productData, $language);

    // 5. Find our specific image in the result and check its legend
    $foundLegend = null;
    foreach ($images as $img) {
        if (isset($img['id_image']) && $img['id_image'] == $id_image) {
            $foundLegend = isset($img['legend']) ? $img['legend'] : null;
            break;
        }
    }

    echo "Product Name: " . $product->name . "\n";
    echo "Image ID: $id_image\n";
    echo "Observed Legend: " . ($foundLegend === null ? 'NULL' : "'$foundLegend'") . "\n";

    // The bug: if legend is empty in DB, it remains empty in the result because 
    // the raw image data (empty legend) overwrote the resolved legend (product name).
    // The fix: the resolved legend from getImage() should overwrite the empty one.
    if ($foundLegend === 'Product Test Name') {
        echo "SUCCESS: Legend was correctly filled with product name.\n";
        exit(0);
    } else {
        echo "FAILURE: Legend is empty or incorrect.\n";
        exit(1);
    }

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
