<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #27781, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * The bug is that customers imported via CSV have date_upd = '0000-00-00 00:00:00'.
 * This causes a CustomerException when trying to save the customer in the Back Office.
 * The fix is to add 'date_upd' to the default values in AdminImportController.
 */

// To avoid "Call to a member function isLoggedBack() on null", we must populate the employee in Context
$employee = new Employee(1);
if (!Validate::isLoadedObject($employee)) {
    $employee->firstname = 'Test';
    $employee->lastname = 'Test';
    $employee->email = 'test@test.com';
    $employee->passwd = '123456';
    $employee->add();
}
Context::getContext()->employee = $employee;

// Set the entity to 'Customers' (index 3 in the entities array)
$_GET['entity'] = 3;

try {
    // Ensure the controller class is loaded
    if (!class_exists('AdminImportController')) {
        require_once 'controllers/admin/AdminImportController.php';
    }

    $controller = new AdminImportController();

    // Use Reflection to access the protected static property $default_values
    $ref = new ReflectionClass('AdminImportController');
    $prop = $ref->getProperty('default_values');
    $prop->setAccessible(true);
    
    // Get the value of the static property
    $defaults = $prop->getValue();

    echo "Checking AdminImportController::\$default_values\n";
    if (isset($defaults['date_upd'])) {
        echo "date_upd is set to: " . $defaults['date_upd'] . "\n";
    } else {
        echo "date_upd is NOT set in default_values\n";
    }

    // The fix is the presence of 'date_upd' in the default values array
    if (isset($defaults['date_upd']) && !empty($defaults['date_upd'])) {
        exit(0);
    } else {
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error during test execution: " . $t->getMessage() . "\n";
    exit(1);
}
