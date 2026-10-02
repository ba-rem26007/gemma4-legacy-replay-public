<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29660, validé pre/post automatiquement
require 'config/config.inc.php';

// Ensure the Composer autoloader is loaded for Symfony classes in CLI
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
}

use PrestaShop\PrestaShop\Core\Module\ModuleRepository;
use PrestaShop\PrestaShop\Core\Module\ModuleDataProvider;
use PrestaShop\PrestaShop\Core\Module\AdminModuleDataProvider;
use PrestaShop\PrestaShop\Core\Module\CacheProvider;
use PrestaShop\PrestaShop\Core\Hook\HookManager;

try {
    // We use anonymous classes to mock the dependencies.
    // These will only work if the autoloader has successfully loaded the base classes.
    
    $moduleDataProvider = new class extends ModuleDataProvider {
        public function getInstalled(): array { return []; }
        public function __construct() {}
    };

    $adminModuleDataProvider = new class extends AdminModuleDataProvider {
        public function __construct() {}
    };

    $cacheProvider = new class extends CacheProvider {
        public function __construct() {}
    };

    $hookManager = new class extends HookManager {
        public function exec($hook, $params = [], $id_module = null, $return_all = false) {
            if ($hook === 'actionListModules') {
                // This specific return value triggers the bug:
                // Before fix: empty([null]) is false -> array_merge(...[null]) -> TypeError
                // After fix: empty(reset([null])) is true -> returns [] -> No error
                return [null];
            }
            return [];
        }
        public function __construct() {}
    };

    // Instantiate the repository with the anonymous stubs
    $repository = new ModuleRepository(
        $moduleDataProvider,
        $adminModuleDataProvider,
        $cacheProvider,
        $hookManager,
        '/var/www/html/modules/'
    );

    // getList() calls addModulesFromHook() -> getModulesFromHook()
    // This is where the array_merge(...$modulesFromHook) is executed.
    $repository->getList();

    echo "Success: No exception thrown. The bug is fixed.\n";
    exit(0);
} catch (\Throwable $t) {
    echo "Failure: Exception caught: " . get_class($t) . " - " . $t->getMessage() . "\n";
    echo "File: " . $t->getFile() . " line " . $t->getLine() . "\n";
    exit(1);
}
