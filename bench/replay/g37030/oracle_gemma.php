<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37030, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context
$context = Context::getContext();
$context->employee = new Employee(1);
$context->shop = new Shop(1);
$context->language = new Language(1);

/**
 * We create a wrapper class to bypass the protected visibility of $object 
 * in AdminController, as we cannot modify the core class.
 */
class TestAdminShopGroupController extends AdminShopGroupController
{
    public function setObject($obj)
    {
        $this->object = $obj;
    }
}

// Create a ShopGroup object in memory.
// We set id = 1 and active = 0 (disabled) to trigger the bug.
$sg = new ShopGroup();
$sg->id = 1;
$sg->active = 0;

// Instantiate the wrapper controller.
$controller = new TestAdminShopGroupController();
$controller->setObject($sg);
$controller->display = 'edit';

// Trigger the method that contains the bug.
// renderForm() populates $this->fields_value.
try {
    $controller->renderForm();
} catch (\Throwable $e) {
    echo "Error during renderForm: " . $e->getMessage() . "\n";
    exit(1);
}

// The bug is that 'active' was hardcoded to true in fields_value.
// After the fix, it should reflect the object's active status.
$observed = isset($controller->fields_value['active']) ? $controller->fields_value['active'] : null;

echo "ShopGroup ID: " . (int)$sg->id . "\n";
echo "ShopGroup active status: " . (int)$sg->active . "\n";
echo "Form field 'active' value: " . ($observed === true ? 'true' : ($observed === false ? 'false' : 'null')) . "\n";

// If the bug is fixed, $observed should be false (matching $sg->active).
// If the bug is present, $observed will be true.
exit($observed === false ? 0 : 1);
