<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #28416, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Core\Module\ModuleRepository;
use PrestaShop\PrestaShop\Adapter\Module\ModuleDataProvider;
use PrestaShop\PrestaShop\Adapter\Module\AdminModuleDataProvider;
use PrestaShop\PrestaShop\Core\Module\CacheProvider;
use PrestaShop\PrestaShop\Core\Module\HookManager;

// 1. Setup: Create a "broken" module on disk.
// The module exists, but its constructor throws an exception.
$moduleName = 'brokenmodule';
$moduleDir = '/var/www/html/modules/' . $moduleName;

if (!is_dir($moduleDir)) {
    mkdir($moduleDir, 0777, true);
}

$moduleFile = $moduleDir . '/' . $moduleName . '.php';
$classContent = "<?php
class $moduleName extends Module {
    public function __construct() {
        throw new Exception('Intentional crash in module constructor');
    }
}";
file_put_contents($moduleFile, $classContent);

// 2. Mock dependencies for ModuleRepository
// We override the constructors to avoid ArgumentCountError from parent classes
$moduleDataProvider = new class extends ModuleDataProvider {
    public function __construct() {} 
    public function isModuleMainClassValid($name) {
        return true; 
    }
};

$adminModuleDataProvider = new class extends AdminModuleDataProvider {
    public function __construct() {}
};

// CacheProvider is an interface, so we implement it.
$cacheProvider = new class implements CacheProvider {
    public function contains($key) { return false; }
    public function fetch($key) { return null; }
    public function save($key, $value) { }
};

$hookManager = new class extends HookManager {
    public function __construct() {}
};

$modulePath = '/var/www/html/modules';

// 3. Instantiate the Repository
$repository = new ModuleRepository(
    $moduleDataProvider,
    $adminModuleDataProvider,
    $cacheProvider,
    $hookManager,
    $modulePath
);

// 4. Execution
try {
    echo "Attempting to get module '$moduleName'...\n";
    // This method calls getModuleAttributes(), which calls ModuleLegacy::getInstanceByName()
    // If the bug is present, the exception from the module constructor will not be caught.
    $module = $repository->getModule($moduleName);
    echo "Success: Module retrieved without crashing.\n";
    
    // Cleanup
    @unlink($moduleFile);
    @rmdir($moduleDir);
    
    exit(0); // Corrected: The exception was caught internally by the fix
} catch (\Throwable $t) {
    echo "Caught exception: " . $t->getMessage() . "\n";
    
    // Cleanup
    @unlink($moduleFile);
    @rmdir($moduleDir);
    
    // If we are here, the exception bubbled up from the module constructor, meaning the fix is missing.
    exit(1); // Bug still present
}
