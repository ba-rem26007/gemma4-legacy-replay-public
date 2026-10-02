<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #35321, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Test for Ticket: Disallow display of category from other shop
 * The goal is to verify that a category associated with Shop 1 
 * cannot be accessed when the context is Shop 2 (should trigger 404).
 */

try {
    $context = Context::getContext();
    $context->language = new Language(1);
    $context->customer = new Customer(1);

    // 1. Setup: Create a category associated ONLY with Shop 1
    // We switch to Shop 1 context to create the category
    Shop::setContext(Shop::CONTEXT_SHOP, 1);
    $context->shop = new Shop(1);

    $cat = new Category();
    $cat->name = [1 => 'Test Shop 1 Category'];
    $cat->link_rewrite = [1 => 'test-shop-1-category']; // Required to avoid validation error
    $cat->active = 1;
    $cat->id_parent = 2; // Use a standard parent ID (usually Home)
    $cat->add();
    $catId = $cat->id;

    echo "Created Category ID $catId associated with Shop 1\n";

    // 2. Execution: Try to access this category while in Shop 2 context
    Shop::setContext(Shop::CONTEXT_SHOP, 2);
    $context->shop = new Shop(2);

    // Simulate the GET parameter for the controller
    $_GET['id_category'] = $catId;

    // We extend the controller to access the protected 'notFound' property
    if (!class_exists('TestCategoryController')) {
        class TestCategoryController extends CategoryController {
            public function isNotFound() {
                return $this->notFound;
            }
        }
    }

    $controller = new TestCategoryController();
    
    // init() is where the check for existsInShop is performed
    // We use @ to suppress header() warnings in CLI
    @$controller->init();

    $notFound = $controller->isNotFound();
    echo "Current Shop ID: " . $context->shop->id . "\n";
    echo "Category exists in Shop 2: " . ($cat->existsInShop(2) ? 'Yes' : 'No') . "\n";
    echo "Controller notFound status: " . ($notFound ? 'true' : 'false') . "\n";

    // If the fix is working, notFound should be true (404)
    if ($notFound) {
        echo "Success: Category from other shop is correctly blocked (404).\n";
        exit(0);
    } else {
        echo "Failure: Category from other shop is still accessible.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Exception occurred: " . $t->getMessage() . "\n";
    exit(1);
}
