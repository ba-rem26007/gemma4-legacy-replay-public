<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29161, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

// Create a Profile with no permissions
$profile = new Profile();
$profile->name = [1 => 'Stagiaire Read Only'];
$profile->add();

// Create an Employee with this profile
$employee = new Employee();
$employee->firstname = 'Test';
$employee->lastname = 'Employee';
$employee->email = 'test@example.com';
$employee->passwd = '123456';
$employee->id_profile = $profile->id;
$employee->id_lang = 1; // Required field
$employee->add();

// Log the employee in
Context::getContext()->employee = $employee;

// Target Order State
$id_order_state = 1;
$initial_send_email = (int) Db::getInstance()->getValue('SELECT send_email FROM ' . _DB_PREFIX_ . 'order_state WHERE id_order_state = ' . $id_order_state);

echo "Initial send_email for state $id_order_state: $initial_send_email\n";

// Simulate the AJAX request
$_GET['id_order_state'] = $id_order_state;

try {
    $controller = new AdminStatusesController();
    
    ob_start();
    $controller->ajaxProcessSendEmailOrderState();
    $output = ob_get_clean();
    
    $response = json_decode($output, true);
    $final_send_email = (int) Db::getInstance()->getValue('SELECT send_email FROM ' . _DB_PREFIX_ . 'order_state WHERE id_order_state = ' . $id_order_state);
    
    echo "Response: " . $output . "\n";
    echo "Final send_email for state $id_order_state: $final_send_email\n";

    // The bug is FIXED if:
    // 1. The response indicates failure (success == 0)
    // 2. The database value has NOT changed
    if (isset($response['success']) && $response['success'] == 0 && $initial_send_email === $final_send_email) {
        echo "SUCCESS: Access denied as expected.\n";
        exit(0);
    } else {
        echo "FAILURE: Employee was able to modify the status or received a success response.\n";
        exit(1);
    }
} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
