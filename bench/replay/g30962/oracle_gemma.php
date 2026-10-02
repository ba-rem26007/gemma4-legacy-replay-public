<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #30962, validé pre/post automatiquement
require 'config/config.inc.php';

use Symfony\Component\Routing\Exception\RouteNotFoundException;

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

$employee = new Employee(1);
if (!Validate::isLoadedObject($employee)) {
    $employee = new Employee();
    $employee->firstname = 'Admin';
    $employee->lastname = 'Admin';
    $employee->email = 'admin@example.com';
    $employee->passwd = 'password';
    $employee->add();
}
$context->employee = $employee;

// We create a Tab that points to a non-existent Symfony route to trigger the bug.
// We use Db::getInstance()->insert to avoid issues with protected properties in the Tab ObjectModel.
// We only include columns that are standard in the ps_tab table.
Db::getInstance()->insert('tab', [
    'class_name' => 'AdminModules',
    'module' => '',
    'id_parent' => 0,
    'id_profile' => 1,
    'route_name' => 'non_existent_route_trigger_bug_12345',
]);

// Instantiate AdminController.
// We pass a controller name to ensure the internal logic (token, etc.) doesn't fail.
$controller = new AdminController('AdminModules');

try {
    /**
     * initHeader() calls getTabs(), which iterates through the tabs in the database.
     * For each tab, it calls $this->context->link->getTabLink($tab).
     * If route_name is set but the route doesn't exist in the Symfony router, 
     * getTabLink throws a RouteNotFoundException.
     * 
     * Before the fix: This exception is uncaught and crashes the page.
     * After the fix: This exception is caught, the tab is removed, and the process continues.
     */
    $controller->initHeader();
    
    echo "Success: RouteNotFoundException was handled by AdminController.\n";
    exit(0);
} catch (RouteNotFoundException $e) {
    echo "Failure: RouteNotFoundException was NOT handled: " . $e->getMessage() . "\n";
    exit(1);
} catch (\Throwable $t) {
    echo "Failure: An unexpected error occurred: " . $t->getMessage() . "\n";
    exit(1);
}
