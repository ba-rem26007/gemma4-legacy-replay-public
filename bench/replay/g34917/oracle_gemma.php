<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #34917, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

// Admin controllers require an authenticated employee in the context
$employee = new Employee(1);
if (!Validate::isLoadedObject($employee)) {
    $employee = new Employee();
    $employee->firstname = 'Test';
    $employee->lastname = 'Employee';
    $employee->email = 'test@example.com';
    $employee->passwd = 'password';
    $employee->add();
}
Context::getContext()->employee = $employee;

try {
    // Instantiate the controller
    $controller = new AdminGroupsController();
    
    // Fix: $currentIndex is a static property
    AdminGroupsController::$currentIndex = 'index.php';
    
    // Fix: $token is public
    $controller->token = 'test_token';
    
    // Fix: $display is a protected property. Use Reflection to set it.
    $reflectionDisplay = new ReflectionProperty('AdminGroupsController', 'display');
    $reflectionDisplay->setAccessible(true);
    $reflectionDisplay->setValue($controller, 'options');
    
    echo "Initial display state: options\n";

    // Execute the method where the fix is applied
    // The fix should change $this->display from 'options' to ''
    $controller->initProcess();
    
    // Verify the value of $display after initProcess() using Reflection
    $displayAfter = $reflectionDisplay->getValue($controller);
    echo "Display state after initProcess(): '" . $displayAfter . "'\n";

    // Execute the method that decides whether to show the "Add new group" button
    // This method checks if (Group::isFeatureActive() && empty($this->display))
    $controller->initPageHeaderToolbar();

    // Check if the 'new_group' button is present in the toolbar
    // $page_header_toolbar_btn is public in AdminController
    $hasAddButton = isset($controller->page_header_toolbar_btn['new_group']);
    
    if ($hasAddButton) {
        echo "SUCCESS: 'Add new group' button is present.\n";
        exit(0);
    } else {
        echo "FAILURE: 'Add new group' button is missing. Display was likely not reset.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
