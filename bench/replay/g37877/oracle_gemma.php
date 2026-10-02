<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37877, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\LegacyContext;
use PrestaShop\PrestaShop\Adapter\Category\CategoryDataProvider;

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);

// Ensure Category 2 exists and is active
$category = new Category(2);
if (!Validate::isLoadedObject($category)) {
    $category = new Category();
    $category->id_parent = 1;
    $category->name = [1 => 'Test Category'];
    $category->active = 1;
    $category->add();
} else {
    $category->active = 1;
    $category->save();
}
$catId = (int)$category->id;

// Ensure the category image file does NOT exist to trigger the bug
$imagePath = _PS_CAT_IMG_DIR_ . $catId . '.jpg';
if (file_exists($imagePath)) {
    unlink($imagePath);
}

echo "Testing Category ID: $catId\n";
echo "Image file exists: " . (file_exists($imagePath) ? 'Yes' : 'No') . "\n";

try {
    // Instantiate the adapter manually as Symfony container is not available in CLI
    $legacyContext = new LegacyContext($context);
    $dataProvider = new CategoryDataProvider($legacyContext);

    // Call the method touched by the fix
    // getAjaxCategories is used in the BO product page for category redirection search
    $results = $dataProvider->getAjaxCategories('', 10);

    $foundCategory = null;
    foreach ($results as $res) {
        if ($res['id'] == $catId) {
            $foundCategory = $res;
            break;
        }
    }

    if (!$foundCategory) {
        echo "Error: Category $catId not found in AJAX results\n";
        exit(1);
    }

    $imageUrl = $foundCategory['image'];
    echo "Observed image URL: $imageUrl\n";

    // The fix should return the default category image if the specific one is missing
    // Before fix: it called getCatImageLink which returns a URL containing the category ID (e.g., /catimg/2.jpg)
    // After fix: it calls getMediaLink with 'default-category_default.jpg'
    if (strpos($imageUrl, 'default-category_default') !== false) {
        echo "SUCCESS: Default image used when category image is missing.\n";
        exit(0);
    } else {
        echo "FAILURE: Category-specific image URL returned despite file being missing.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Exception caught: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
    exit(1);
}
