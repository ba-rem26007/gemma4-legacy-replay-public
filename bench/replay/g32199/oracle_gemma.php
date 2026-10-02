<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #32199, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\Product\Image\Repository\ProductImageRepository;
use PrestaShop\PrestaShop\Adapter\Product\Repository\ProductRepository;
use PrestaShop\PrestaShop\Adapter\Product\Image\Validator\ProductImageValidator;
use PrestaShop\PrestaShop\Core\Domain\Product\ProductId;

// Setup Context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

try {
    $productId = 1;
    
    // 1. Clean up existing images for this product to avoid unique constraint conflicts
    // ps_image has a unique key on (id_product, cover) where cover=1
    Db::getInstance()->execute("DELETE FROM ps_image_shop WHERE id_image IN (SELECT id_image FROM ps_image WHERE id_product = " . (int)$productId . ")");
    Db::getInstance()->execute("DELETE FROM ps_image WHERE id_product = " . (int)$productId);

    // 2. Create a product image that is NOT a cover
    // In PrestaShop, Image::add() automatically creates the entry in ps_image_shop for the current shop
    $image = new Image();
    $image->id_product = (int)$productId;
    $image->position = 1;
    $image->cover = 0; 
    $image->add();

    // Double check that it is indeed not a cover in either table
    $globalCover = Db::getInstance()->getValue("SELECT cover FROM ps_image WHERE id_image = " . (int)$image->id);
    $shopCover = Db::getInstance()->getValue("SELECT cover FROM ps_image_shop WHERE id_image = " . (int)$image->id . " AND id_shop = 1");

    echo "Image created with ID: {$image->id}. Global cover: $globalCover, Shop cover: $shopCover\n";

    // 3. Instantiate the Repository and its dependencies
    // Use the Doctrine connection required by the Repository
    $connection = \PrestaShop\PrestaShop\Adapter\Db\Db::getInstance();
    $dbPrefix = _DB_PREFIX_;
    $productRepository = new ProductRepository($connection, $dbPrefix);
    $productImageValidator = new ProductImageValidator();
    
    $repo = new ProductImageRepository(
        $connection,
        $dbPrefix,
        $productRepository,
        $productImageValidator
    );

    // 4. Trigger the fix: updateMissingCovers
    // It should detect that no global cover exists (coverIdGlobal === null)
    // and set the first available image as cover in ps_image.
    $repo->updateMissingCovers(new ProductId($productId));

    // 5. Verify the result in the database
    $observedCover = Db::getInstance()->getValue("SELECT cover FROM ps_image WHERE id_image = " . (int)$image->id);
    
    echo "Observed cover value in ps_image after updateMissingCovers: $observedCover\n";

    // The test passes if the cover was updated to 1
    if ($observedCover == 1) {
        exit(0);
    } else {
        echo "Error: The global cover was not updated to 1.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Exception: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
    exit(1);
}
