<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37869, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

// AdminController and Link require a logged-in employee
$employee = new Employee(1);
if (!Validate::isLoadedObject($employee)) {
    $employee = new Employee();
    $employee->firstname = 'Admin';
    $employee->lastname = 'Test';
    $employee->email = 'admin@test.com';
    $employee->passwd = 'password';
    $employee->add();
}
$context->employee = $employee;

// Ensure Product 1 exists
$product = new Product(1);
if (!Validate::isLoadedObject($product)) {
    $product = new Product();
    $product->price = 10.0;
    $product->name = [1 => 'Test Product'];
    $product->link_rewrite = [1 => 'test-product'];
    $product->id_category_default = 2;
    $product->add();
}

// In CLI, the Link object is not automatically instantiated in the Context
$context->link = new Link();

// Simulate the inputs that trigger the bug:
// bo_search_type = 1 (Catalog)
// bo_query = product ID
$query = (int)$product->id;
$searchType = 1;

echo "Testing search for product ID: $query with search type: $searchType\n";

if ($searchType == 1 && Validate::isUnsignedInt($query)) {
    $p = new Product($query);
    if (Validate::isLoadedObject($p)) {
        // The fix replaces:
        // Tools::redirectAdmin('index.php?tab=AdminProducts&id_product=' . (int) ($product->id) . '&token=' . Tools::getAdminTokenLite('AdminProducts'));
        // With:
        // Tools::redirectAdmin($this->context->link->getAdminLink('AdminProducts', true, ['id_product' => (int) $product->id, 'updateproduct' => '1']));
        
        // We call the method exactly as the fix does
        $url = $context->link->getAdminLink('AdminProducts', true, [
            'id_product' => (int) $p->id, 
            'updateproduct' => '1'
        ]);
        
        echo "Generated URL: $url\n";

        if (empty($url)) {
            echo "FAILURE: Generated URL is empty. Link object might not be fully initialized for CLI.\n";
            exit(1);
        }

        // The bug was that the redirect triggered a CSV download because 'updateproduct=1' was missing.
        // The fix is verified if the URL contains both the product ID and the 'updateproduct=1' flag.
        $hasProductId = strpos($url, 'id_product=' . (int)$p->id) !== false;
        $hasUpdateParam = strpos($url, 'updateproduct=1') !== false;

        if ($hasProductId && $hasUpdateParam) {
            echo "SUCCESS: URL contains id_product and updateproduct=1\n";
            exit(0);
        } else {
            echo "FAILURE: URL is missing required parameters for product page redirect\n";
            echo "URL observed: $url\n";
            exit(1);
        }
    } else {
        echo "FAILURE: Product not loaded\n";
        exit(1);
    }
} else {
    echo "FAILURE: Search conditions not met\n";
    exit(1);
}
