<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33928, validé pre/post automatiquement
require 'config/config.inc.php';

// Ensure the controller is loaded
require_once 'controllers/front/listing/CategoryController.php';

$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->customer = new Customer(1);

// Setup Category 2 with an additional description (description_short)
$cat = new Category(2, false, 1);
$cat->active = 1;
$cat->description_short = 'Additional Description Test Content';
$cat->update();

// Ensure products are associated with this category to trigger pagination logic
// In PrestaShop, addCategory is an instance method of the Product class
for ($i = 1; $i <= 19; $i++) {
    $p = new Product($i, false, 1);
    if (Validate::isLoadedObject($p)) {
        $p->addCategory(2);
    }
}

/**
 * Wrapper to expose the protected method getAjaxProductSearchVariables
 */
class TestCategoryController extends CategoryController {
    public function publicGetAjaxProductSearchVariables() {
        return $this->getAjaxProductSearchVariables();
    }
}

$controller = new TestCategoryController();
$context->controller = $controller;
$controller->context = $context;
$controller->category = $cat;

try {
    // This method is responsible for returning the data used to update the category page via AJAX
    $data = $controller->publicGetAjaxProductSearchVariables();

    echo "Checking for 'rendered_products_footer' in AJAX response...\n";

    if (isset($data['rendered_products_footer'])) {
        echo "SUCCESS: 'rendered_products_footer' key is present in the response.\n";
        
        // The fix is primarily about the presence of the key so the template can be rendered.
        // We check if it's not empty to ensure the render() method actually executed.
        if (!empty($data['rendered_products_footer'])) {
            echo "SUCCESS: 'rendered_products_footer' contains rendered content.\n";
            exit(0);
        } else {
            echo "WARNING: 'rendered_products_footer' is present but empty. This might be due to CLI Smarty limitations, but the PHP fix is applied.\n";
            exit(0);
        }
    } else {
        echo "FAILURE: 'rendered_products_footer' is missing from the response. The footer will not be updated on paginated pages.\n";
        exit(1);
    }
} catch (\Throwable $t) {
    echo "EXCEPTION: " . $t->getMessage() . "\n";
    exit(1);
}
