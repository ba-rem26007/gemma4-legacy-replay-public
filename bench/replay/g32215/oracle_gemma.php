<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #32215, validé pre/post automatiquement
require 'config/config.inc.php';

// Define missing constants required by AdminController in CLI mode
if (!defined('_PS_ADMIN_DIR_')) {
    define('_PS_ADMIN_DIR_', _PS_ROOT_DIR_ . '/admin/');
}

/**
 * To test the fix without the Symfony container (which causes "Call to a member function get() on null" 
 * in the trans() method), we create a mock controller that intercepts the translation call.
 */
class TestAdminController extends AdminController
{
    public $last_trans_params = [];

    /**
     * Override trans to avoid calling the Symfony container and to capture the arguments.
     */
    public function trans($string, array $params = [], $domain = null)
    {
        $this->last_trans_params = $params;
        return $string;
    }
}

$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);

// Ensure an employee is logged in
$employee = new Employee(1);
if (!Validate::isLoadedObject($employee)) {
    $employee = new Employee();
    $employee->firstname = 'Test';
    $employee->lastname = 'Test';
    $employee->email = 'test@test.com';
    $employee->passwd = 'password';
    $employee->add();
}
$context->employee = $employee;

try {
    $controller = new TestAdminController();
    $controller->context = $context;

    // getNotificationTip is a private method. We use Reflection to call it.
    $reflection = new ReflectionClass('AdminController');
    $method = $reflection->getMethod('getNotificationTip');
    $method->setAccessible(true);

    // Trigger the code path for 'order' which contains the abandoned carts tip
    $method->invoke($controller, 'order');

    $params = $controller->last_trans_params;
    echo "Translation parameters captured: " . print_r($params, true) . "\n";

    // The fix is the addition of '_raw' => true in the translation parameters
    if (isset($params['_raw']) && $params['_raw'] === true) {
        echo "Result: Fixed. '_raw' => true is passed to the translator.\n";
        exit(0);
    } else {
        echo "Result: Bug detected. '_raw' => true is missing.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Exception caught: " . $t->getMessage() . "\n";
    exit(1);
}
