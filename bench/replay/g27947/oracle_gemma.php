<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #27947, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->customer = new Customer(1);

// 1. Setup Category FIRST. 
// We do this before mocking the theme because Category::save() triggers hooks 
// that call the translator, which expects $context->shop->theme to be a real Theme object.
$category = new Category(2);
$category->active = 0;
$category->save();

// 2. Now we mock the theme to control the return value of getLayoutRelativePathForPage.
// We do this AFTER save() to avoid the TypeError in the translation system.
$context->shop->theme = new class {
    public function getLayoutRelativePathForPage($page) {
        if ($page === 'error') {
            return 'layouts/layout-custom-error.tpl';
        }
        return 'layouts/layout-default.tpl';
    }
};

// Instantiate the controller
if (!class_exists('CategoryController')) {
    require_once _PS_FRONT_DIR_ . 'listing/CategoryController.php';
}
$controller = new CategoryController();

/**
 * Helper to set protected/private properties since we are in a CLI test
 */
$setProtectedProperty = function($object, $propertyName, $value) {
    $reflection = new ReflectionClass($object);
    if ($reflection->hasProperty($propertyName)) {
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    } else {
        $object->$propertyName = $value;
    }
};

try {
    // Inject dependencies into the controller to trigger the bug in getLayout()
    $setProtectedProperty($controller, 'context', $context);
    $setProtectedProperty($controller, 'category', $category);
    $setProtectedProperty($controller, 'notFound', true);

    $layout = $controller->getLayout();
    $expected = 'layouts/layout-custom-error.tpl';

    echo "Observed layout: $layout\n";
    echo "Expected layout: $expected\n";

    // The bug is the hardcoded string 'layouts/layout-full-width.tpl'.
    // If the observed layout is the hardcoded one, the fix is not applied.
    if ($layout === 'layouts/layout-full-width.tpl') {
        echo "Bug detected: layout is hardcoded to layout-full-width.tpl\n";
        exit(1);
    }

    if ($layout === $expected) {
        echo "Success: layout is correctly retrieved from theme\n";
        exit(0);
    }

    echo "Unexpected layout returned: $layout\n";
    exit(1);

} catch (\Throwable $t) {
    echo "Exception caught: " . $t->getMessage() . "\n";
    exit(1);
}
