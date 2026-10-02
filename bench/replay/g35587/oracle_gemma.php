<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #35587, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);
$employee = new Employee(1);
Context::getContext()->employee = $employee;

/**
 * Dummy AdminController to trigger the bug.
 * We override init() to prevent the parent AdminController::init() from being called.
 * In the parent init(), $this->action is typically assigned via Tools::getValue('action').
 * By overriding it and doing nothing, $this->action remains null, which triggers the bug
 * when postProcess() is called and an 'action' is present in the request.
 */
class TestAdminController extends AdminController
{
    public function __construct()
    {
        $this->controller_name = 'TestAdminController';
        parent::__construct();
    }

    public function init()
    {
        // Do nothing. This ensures $this->action is NOT initialized (remains null).
        // It also avoids checkAccess() and other heavy logic not suitable for CLI.
    }

    public function ajaxProcessTest()
    {
        return 'success';
    }
}

// Simulate the request parameters
$_GET['action'] = 'test';

// Initialize the controller
$controller = new TestAdminController();
$controller->ajax = true;

// Error handler to capture the PHP 8.1 deprecation notice
$deprecationTriggered = false;
set_error_handler(function ($errno, $errstr) use (&$deprecationTriggered) {
    if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
        $deprecationTriggered = true;
    }
    return true;
});

try {
    echo "Executing postProcess()...\n";
    // This should enter the first if block:
    // 1. $this->ajax is true
    // 2. $action = Tools::getValue('action') is 'test'
    // 3. method_exists($this, 'ajaxProcessTest') is true
    // Then it calls ucfirst($this->action) where $this->action is null.
    $result = $controller->postProcess();
    echo "Result: $result\n";
} catch (\Throwable $t) {
    echo "Exception caught: " . $t->getMessage() . "\n";
    restore_error_handler();
    exit(1);
}

restore_error_handler();

if ($deprecationTriggered) {
    echo "Bug detected: ucfirst() received null from \$this->action\n";
    exit(1);
} else {
    echo "No deprecation notice observed. Bug fixed.\n";
    exit(0);
}
