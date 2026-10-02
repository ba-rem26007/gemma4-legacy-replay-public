<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #30948, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\Module\AdminModuleDataProvider;
use PrestaShop\PrestaShop\Adapter\Module\ModuleDataProvider;
use PrestaShop\PrestaShop\Core\Module\ModuleCollection;
use PrestaShopBundle\Service\DataProvider\Admin\CategoriesProvider;

/**
 * Since Module is an abstract class, we create a concrete implementation for testing.
 * This module is non-configurable (no getContent) and non-upgradable.
 */
class TestNonConfigurableModule extends Module
{
    public function __construct()
    {
        // Based on the error "Argument #2 ($context) must be of type ?Context", 
        // we pass the context as the second argument.
        parent::__construct('test_non_configurable_module', Context::getContext());
        $this->version = '1.0.0';
    }
    // No getContent() method -> non-configurable
    // No upgrade() method -> non-upgradable
}

try {
    // 1. Setup Employee
    $employee = new Employee(1);
    if (!Validate::isLoadedObject($employee)) {
        $employee = new Employee();
        $employee->id_profile = 1;
        $employee->lastname = 'Test';
        $employee->firstname = 'Test';
        $employee->email = 'test@test.com';
        $employee->passwd = '1234';
        $employee->id_lang = 1;
        $employee->add();
    }

    // 2. Create and enable the module in the database
    $m = new TestNonConfigurableModule();
    $m->active = 1;
    $m->add(); 
    $m->update();

    // 3. Instantiate AdminModuleDataProvider
    // We use Reflection to instantiate providers without their complex dependencies
    $catProv = (new ReflectionClass(CategoriesProvider::class))->newInstanceWithoutConstructor();
    $modProv = (new ReflectionClass(ModuleDataProvider::class))->newInstanceWithoutConstructor();
    
    $dataProvider = new AdminModuleDataProvider($catProv, $modProv, $employee);

    // 4. Prepare ModuleCollection
    $collection = new ModuleCollection();
    $collection[] = $m;

    // 5. Execute the logic that determines the default action
    // setActionUrls filters the $moduleActions array based on module capabilities
    $dataProvider->setActionUrls($collection);

    // 6. Verify the first action for the module
    $moduleInCollection = reset($collection);
    if (!isset($moduleInCollection->actions) || empty($moduleInCollection->actions)) {
        echo "Error: No actions were assigned to the module.\n";
        exit(1);
    }

    $actions = $moduleInCollection->actions;
    $firstAction = $actions[0];

    echo "Module: " . $m->name . "\n";
    echo "First action observed: $firstAction\n";
    echo "Expected action: " . Module::ACTION_DISABLE . "\n";

    // The test passes if the first action is 'disable'
    // Before the fix, for an enabled non-configurable module, the first action was 'disable_mobile'.
    if ($firstAction === Module::ACTION_DISABLE) {
        exit(0);
    } else {
        echo "Bug: The default action is '$firstAction' instead of '" . Module::ACTION_DISABLE . "'.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
