<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #27758, validé pre/post automatiquement
require 'config/config.inc.php';

// Context setup
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

try {
    // 1. Create a product with NO name and NO link_rewrite to trigger the bug
    $p = new Product();
    $p->price = 10.0;
    $p->name = []; // Empty for all languages
    $p->link_rewrite = []; // Empty for all languages
    $p->id_category_default = 2;
    $p->active = 1;
    $p->add();

    // 2. Create an image for this product
    $i = new Image();
    $i->id_product = $p->id;
    $i->position = 1;
    $i->cover = 1;
    $i->add();

    // Reload product with language context to simulate how it's used in ImageRetriever
    // This ensures $p->name and $p->link_rewrite are strings (empty) rather than arrays
    $p = new Product($p->id, false, 1);

    // 3. Instantiate the ImageRetriever directly (Symfony class)
    // Dependency: Link
    $link = new Link();
    $retriever = new \PrestaShop\PrestaShop\Adapter\Image\ImageRetriever($link);

    // 4. Call the method that generates the image URL
    $result = $retriever->getImage($p, $i->id);

    if (!$result || !isset($result['small']['url'])) {
        echo "Error: No image URL generated.\n";
        exit(1);
    }

    $url = $result['small']['url'];
    echo "Generated URL: $url\n";

    /**
     * BUG ANALYSIS:
     * Before fix: The URL was generated as ".../{id_image}-{type}/.jpg" 
     * because the rewrite was an empty string.
     * After fix: The URL should be ".../{id_image}-{type}/{rewrite}.jpg"
     * where {rewrite} falls back to the image ID if name/link_rewrite are empty.
     */
    
    // The bug is characterized by the URL ending in "/.jpg" (missing the filename part)
    if (strpos($url, '/.jpg') !== false) {
        echo "BUG DETECTED: URL is missing the rewrite fallback (ends with /.jpg)\n";
        exit(1);
    }

    echo "SUCCESS: URL contains a valid rewrite fallback.\n";
    exit(0);

} catch (\Throwable $t) {
    echo "Exception: " . $t->getMessage() . "\n";
    exit(1);
}
