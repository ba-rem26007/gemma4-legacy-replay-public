<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #36802, validé pre/post automatiquement
require 'config/config.inc.php';

$context = Context::getContext();

// Disable multi-shop to avoid complex HelperShop logic that might trigger "getCode() on null"
Configuration::updateValue('PS_MULTISHOP', 0);

// Initialize essential context objects using standard IDs
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);
$context->country = new Country(1);

// Setup Employee
$employee = new Employee(1);
if (!Validate::isLoadedObject($employee)) {
    $employee = new Employee();
    $employee->firstname = 'Test';
    $employee->lastname = 'Employee';
    $employee->email = 'test@example.com';
    $employee->passwd = 'password';
    $employee->add();
}
$context->employee = $employee;

// Ensure Smarty is available in the context
if (!isset($context->smarty) || $context->smarty === null) {
    $context->smarty = new Smarty();
}

// TRIGGER: Clear all quick access links to trigger the bug
Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'quick_access');

// Instantiate AdminController (Legacy)
$controller = new AdminController();
$controller->token = 'test_token';
$controller->table = 'product';
AdminController::$currentIndex = 'index.php';

try {
    // Call the method touched by the fix
    $controller->initHeader();

    // Retrieve the variables assigned to Smarty
    $vars = $context->smarty->getTemplateVars();
    $quick_access = isset($vars['quick_access']) ? $vars['quick_access'] : null;

    echo "Observed quick_access type: " . gettype($quick_access) . "\n";
    echo "Observed quick_access value: " . var_export($quick_access, true) . "\n";

    /**
     * BUG: Before the fix, if quick_access was empty, it was assigned 'false' (boolean).
     * This caused a TypeError in PHP 8 when the template tried to iterate over it.
     * FIX: It must be an empty array '[]'.
     */
    if (is_array($quick_access)) {
        exit(0);
    } else {
        echo "Failure: quick_access should be an array, but is " . gettype($quick_access) . "\n";
        exit(1);
    }
} catch (\Throwable $t) {
    echo "Fatal error encountered: " . $t->getMessage() . "\n";
    exit(1);
}
