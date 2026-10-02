<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #28535, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);
Context::getContext()->currency = new Currency(1);
Context::getContext()->employee = new Employee(1);

/**
 * Wrapper to access the protected method _deleteOldImages without 
 * triggering the heavy AdminController constructor logic.
 */
class TestImagesController extends AdminImagesController {
    public function __construct() {
        // Override constructor to avoid redirects and permission checks in CLI
    }
    public function callDeleteOldImages($dir, $type, $product = false) {
        return $this->_deleteOldImages($dir, $type, $product);
    }
}

try {
    // 1. Get an existing image for product 1 using PrestaShop API to avoid SQL errors
    $images = Image::getImages(1, 1);
    
    if (empty($images)) {
        // If no image exists, create one. Set cover to 0 to avoid duplicate key constraints
        $image = new Image();
        $image->id_product = 1;
        $image->position = 1;
        $image->cover = 0; 
        if (!$image->add()) {
            echo "Failed to create image\n";
            exit(1);
        }
        $id_image = $image->id;
    } else {
        $id_image = (int)$images[0]['id_image'];
    }

    $image = new Image($id_image);

    // 2. Identify image path and create a dummy 2x file
    $imgFolder = $image->getImgFolder(); 
    $dir = _PS_PRODUCT_IMG_DIR_ . $imgFolder;
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $types = ImageType::getImagesTypes('products');
    if (empty($types)) {
        echo "No image types defined for products\n";
        exit(1);
    }

    $typeName = $types[0]['name'];
    // The bug is that files ending in '2x.jpg' are not matched by the regex in _deleteOldImages
    $filename = $id_image . '-' . $typeName . '2x.jpg';
    $fullPath = $dir . $filename;

    if (!touch($fullPath)) {
        echo "Failed to create dummy file: $fullPath\n";
        exit(1);
    }

    echo "Created dummy 2x image: $fullPath\n";

    // 3. Execute the code touched by the fix
    $controller = new TestImagesController();
    // We pass _PS_PRODUCT_IMG_DIR_ and true for $product to trigger the Image::getAllImages() loop
    $controller->callDeleteOldImages(_PS_PRODUCT_IMG_DIR_, $types, true);

    // 4. Verify if the 2x file was deleted
    $exists = file_exists($fullPath);
    echo "File exists after regeneration attempt: " . ($exists ? 'YES' : 'NO') . "\n";

    // If the file still exists, the regex failed to match the 2x suffix (bug present)
    exit($exists ? 1 : 0);

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
