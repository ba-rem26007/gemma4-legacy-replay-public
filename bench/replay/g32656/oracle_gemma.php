<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #32656, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context
$context = Context::getContext();
$context->employee = new Employee(1);
$context->language = new Language(1);
$context->shop = new Shop(1);
$context->currency = new Currency(1);

// Mock session and request
$_SESSION['id_employee'] = 1;
$_GET['token'] = 'test_token';
unset($_GET['id_cart_rule']); // Ensure we are in "Add new" mode

// Load the controller
require_once 'controllers/admin/AdminCartRulesController.php';

try {
    /**
     * The AdminController constructor and init() methods often trigger 
     * complex environment checks (like the Currency::getDefaultCurrencyId() 
     * error seen previously). To isolate the logic of renderForm(), 
     * we instantiate the class without calling the constructor.
     */
    $reflection = new ReflectionClass('AdminCartRulesControllerCore');
    $controller = $reflection->newInstanceWithoutConstructor();
    
    // Manually inject the context which is required by AdminController methods
    $controller->context = $context;

    // The bug is in the renderForm() method.
    // Before the fix, 'title' was an array: ['Payment:', 'Cart Rules']
    // After the fix, 'title' is a string: 'Cart Rules'
    $form_config = $controller->renderForm();
    
    if (!isset($form_config['title'])) {
        echo "Error: 'title' key not found in renderForm() output\n";
        exit(1);
    }

    $title = $form_config['title'];
    $type = gettype($title);
    
    echo "Observed title type: $type\n";

    if (is_array($title)) {
        echo "Bug detected: 'title' is an array, which triggers the Warning in SmartyLazyRegister::end()\n";
        exit(1);
    }

    if (is_string($title)) {
        echo "Success: 'title' is a string\n";
        exit(0);
    }

    echo "Unexpected type: $type\n";
    exit(1);

} catch (\Throwable $t) {
    echo "Exception caught: " . $t->getMessage() . "\n";
    exit(1);
}
