<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #28540, validé pre/post automatiquement
require 'config/config.inc.php';

// Mock server environment to avoid "Undefined array key" warnings
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['schema'] = 'blank';

// Ensure Context is properly initialized
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

// Convert warnings to exceptions to catch the "Trying to access array offset on value of type null"
set_error_handler(function($errno, $errstr) {
    if (strpos($errstr, 'Trying to access array offset on value of type null') !== false) {
        throw new Exception($errstr);
    }
    return false;
});

try {
    // 1. Setup: Create a category tree with depth > 1
    // Home (2) -> Parent Category -> Child Category
    $catParent = new Category();
    $catParent->name = [1 => 'Parent Category'];
    $catParent->link_rewrite = [1 => 'parent-category'];
    $catParent->id_parent = 2;
    $catParent->active = 1;
    $catParent->add();

    $catChild = new Category();
    $catChild->name = [1 => 'Child Category'];
    $catChild->link_rewrite = [1 => 'child-category'];
    $catChild->id_parent = $catParent->id;
    $catChild->active = 1;
    $catChild->add();

    echo "Categories created: Parent({$catParent->id}) -> Child({$catChild->id})\n";

    // 2. Instantiate WebserviceOutputBuilder
    $builder = new WebserviceOutputBuilder('http://localhost/api/');
    $builder->setObjectRender(new WebserviceOutputXML());

    // 3. Trigger the bug
    // The bug occurs in renderAssociations when depth == 0 and an association is empty.
    // We use renderEntity with depth 0 and fieldsToDisplay 'full' to trigger renderAssociations.
    // This is a public method and avoids potential issues with the getContent loop.
    echo "Calling renderEntity with depth 0 and full fields...\n";
    $builder->setFieldsToDisplay('full');
    $builder->renderEntity($catChild, 0);

    echo "Success: No 'null array offset' or 'get_class(null)' error encountered.\n";
    exit(0);

} catch (\Throwable $t) {
    echo "Caught error: " . $t->getMessage() . "\n";
    
    // The bug manifests as:
    // 1. A warning "Trying to access array offset on value of type null"
    // 2. A TypeError "get_class(): Argument #1 ($object) must be of type object, null given"
    //    (because the old code passed null to renderFlatAssociation, which calls get_class)
    if (strpos($t->getMessage(), 'Trying to access array offset on value of type null') !== false || 
        strpos($t->getMessage(), 'get_class(): Argument #1 ($object) must be of type object, null given') !== false) {
        exit(1);
    }
    
    // Other unexpected errors
    exit(1);
}
