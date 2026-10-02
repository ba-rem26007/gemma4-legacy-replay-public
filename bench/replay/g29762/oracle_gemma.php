<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29762, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

// Ensure an employee is logged in to avoid access denied in AdminController
$employee = new Employee(1);
if (!Validate::isLoadedObject($employee)) {
    $employee = new Employee();
    $employee->firstname = 'Test';
    $employee->lastname = 'User';
    $employee->email = 'test@example.com';
    $employee->passwd = 'password';
    $employee->id_profile = 1; // Superadmin
    $employee->add();
}
Context::getContext()->employee = $employee;

// Create a real Alias object to satisfy AdminController's loadObject()
// This prevents "Call to a member function getAliases() on null"
$alias = new Alias();
$alias->alias = 'test_alias';
$alias->search = 'test_search';
$alias->active = 1;
$alias->add();

// AdminController uses Tools::getValue('id') in its constructor (via init() -> loadObject())
$_GET['id'] = (int)$alias->id;
$_GET['token'] = 'test_token';

try {
    // Instantiate the controller. 
    // The constructor calls parent::__construct() which loads the object from $_GET['id'].
    $controller = new AdminSearchConfControllerCore();
    
    // renderForm() is the method that uses the configuration array containing the bug.
    $html = $controller->renderForm();
    
    // We search for the specific GitHub URL mentioned in the ticket/diff.
    // Using a very specific string to avoid false positives in the controller state.
    $searchString = 'github.com/PrestaShop/PrestaShop/issues/new?template=bug_report.md';
    $found = strpos($html, $searchString) !== false;
    
    echo "Search string '$searchString' found in renderForm(): " . ($found ? 'YES' : 'NO') . "\n";
    
    // If the string is found, the bug is still present (exit 1).
    // If the string is not found, the fix is applied (exit 0).
    exit($found ? 1 : 0);

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
