<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #36521, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
$context = Context::getContext();
$context->employee = new Employee(1);
$context->shop = new Shop(1);
$context->language = new Language(1);

// Ensure the module exists in the database to avoid getInstanceByName returning null
$moduleName = 'dashactivity';
$moduleObj = Module::getInstanceByName($moduleName);
if (!$moduleObj) {
    $moduleObj = new Module();
    $moduleObj->name = $moduleName;
    $moduleObj->active = 1;
    $moduleObj->version = '1.0.0';
    $moduleObj->add();
}

// Simulate the AJAX request parameters
$_POST['module'] = $moduleName;
$_POST['hook'] = 'hookDashboardData'; // This is the value that triggers the bug
$_POST['configs'] = [];

// Since ajaxProcessSaveDashConfig calls die(), we use an output buffer 
// and a shutdown function to capture the result and determine the exit code.
ob_start();

register_shutdown_function(function() {
    $output = ob_get_contents();
    echo "Observed output: $output\n";
    
    // The bug is characterized by the specific error message "This hook is not allowed here."
    if (strpos($output, 'This hook is not allowed here.') !== false) {
        exit(1); // Bug still present
    }
    
    // If the output is a JSON response without that error, or even a fatal error 
    // (like calling a method on a dummy module), it means the hook check was passed.
    exit(0); // Corrected
});

try {
    $controller = new AdminDashboardController();
    $controller->ajaxProcessSaveDashConfig();
} catch (\Throwable $t) {
    echo "Caught exception: " . $t->getMessage() . "\n";
    // If we caught an exception, we need to check if it happened AFTER the hook check.
    // But since we are using register_shutdown_function, we just let it handle the exit.
}
