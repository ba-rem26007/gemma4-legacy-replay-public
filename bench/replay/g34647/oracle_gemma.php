<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #34647, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);
$context->employee = new Employee(1);

// Mock Smarty to prevent "Unable to load template" and method signature errors
// AdminController calls assign() with either 1 argument (array) or 2 arguments (key, value)
$context->smarty = new class {
    public function assign($name, $value = null) { return true; }
    public function fetch($tpl, $assigns = []) { return ''; }
    public function display($tpl, $assigns = []) { return true; }
};

try {
    // Ensure the controller class is loaded
    if (!class_exists('AdminSpecificPriceRuleController')) {
        require_once 'controllers/admin/AdminSpecificPriceRuleController.php';
    }

    $controller = new AdminSpecificPriceRuleController();
    
    // Create a SpecificPriceRule object
    $rule = new SpecificPriceRule();
    // We set an ID so that renderForm() doesn't overwrite price with -1
    $rule->id = 1; 
    // Trigger the bug: empty string for price
    $rule->price = ''; 
    
    // Use Reflection to set the protected 'object' property of the controller
    $reflection = new ReflectionClass($controller);
    $property = $reflection->getProperty('object');
    $property->setAccessible(true);
    $property->setValue($controller, $rule);

    echo "Testing renderForm() with empty price...\n";
    
    // This method contains the buggy line: number_format($value, 6) where $value is ''
    // Before fix: throws TypeError in PHP 8
    // After fix: skips number_format and proceeds to parent::renderForm()
    $controller->renderForm();
    
    echo "Success: renderForm() completed without TypeError.\n";
    exit(0);
} catch (\Throwable $t) {
    echo "Caught exception: " . $t->getMessage() . "\n";
    
    // The bug is a TypeError in number_format() when a string is passed instead of a float
    if (strpos($t->getMessage(), 'number_format') !== false) {
        echo "Bug reproduced: number_format() received a string.\n";
        exit(1);
    }
    
    // If it's a template error, it means we passed the number_format check but the mock failed
    if (strpos($t->getMessage(), 'template') !== false) {
        echo "Passed the bug, but hit a template error.\n";
        exit(0);
    }
    
    exit(1);
}
