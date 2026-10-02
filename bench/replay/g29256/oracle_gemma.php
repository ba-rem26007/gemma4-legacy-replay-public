<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29256, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Custom autoloader for PrestaShop src/ classes to avoid manual require_once ordering issues
 */
spl_autoload_register(function ($class) {
    $prefix = 'PrestaShop\\PrestaShop\\';
    $base_dir = '/var/www/html/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use PrestaShop\PrestaShop\Core\Module\ModuleManager;
use PrestaShop\PrestaShop\Core\Module\ModuleRepository;
use PrestaShop\PrestaShop\Core\Module\ModuleDataProvider;
use PrestaShop\PrestaShop\Core\Module\AdminModuleDataProvider;
use PrestaShop\PrestaShop\Core\Module\SourceHandlerFactory;
use PrestaShop\PrestaShop\Core\Module\HookManager;
use PrestaShop\PrestaShop\Core\Module\ModuleInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Mock of the ModuleInterface
 */
class MockModule implements ModuleInterface {
    public function get($key) { return '1.0.0'; }
    public function onUpgrade($version) { return true; }
    public function onUninstall() { return true; }
    public function onEnable() { return true; }
    public function onDisable() { return true; }
    public function hasValidInstance() { return true; }
    public function getInstance() { return new stdClass(); }
}

// We use anonymous classes that extend the real classes to satisfy type-hints in the constructor
$repoMock = new class extends ModuleRepository {
    public function __construct() {}
    public function getModule($name) { return new MockModule(); }
    public function isInstalled($name) { return true; }
};

$dataProviderMock = new class extends ModuleDataProvider {
    public function __construct() {}
    public function isEnabled($name) { return true; }
};

$adminDataProviderMock = new class extends AdminModuleDataProvider {
    public function __construct() {}
    public function isAllowedAccess($method, $name) { return true; }
};

$sourceFactoryMock = new class extends SourceHandlerFactory {
    public function __construct() {}
};

$translatorMock = new class implements TranslatorInterface {
    public function trans($id, $parameters = [], $domain = null) { return $id; }
    public function getCatalogue() { return []; }
    public function setLocale($locale) {}
    public function setFallbackLocale($locale) {}
};

$dispatcherMock = new class implements EventDispatcherInterface {
    public function dispatch($eventName, $event) { return null; }
    public function addListener($eventName, $listener, $priority = 0) {}
    public function removeListener($eventName, $listener) {}
    public function getListeners($eventName = null) { return []; }
};

$hookManagerMock = new class extends HookManager {
    public function __construct() {}
    public function exec($hook, $params) { return true; }
};

// --- Setup Test Data ---
$moduleName = 'test_upgrade_module';
$modulePath = '/var/www/html/modules/' . $moduleName;

// Create a dummy module file on disk so LegacyModule::getUpgradeStatus can find it and compare versions
if (!is_dir($modulePath)) {
    mkdir($modulePath, 0777, true);
}
$moduleContent = "<?php 
class $moduleName extends Module { 
    public function __construct() { 
        \$this->name = '$moduleName'; 
        \$this->version = '1.0.0'; 
        parent::__construct(); 
    } 
}";
file_put_contents($modulePath . '/' . $moduleName . '.php', $moduleContent);

// Setup DB: Module is already installed with version 1.0.0
Db::getInstance()->execute("DELETE FROM ps_module WHERE name = '$moduleName'");
Db::getInstance()->execute("INSERT INTO ps_module (name, active, version) VALUES ('$moduleName', 1, '1.0.0')");

try {
    $manager = new ModuleManager(
        $repoMock,
        $dataProviderMock,
        $adminDataProviderMock,
        $sourceFactoryMock,
        $translatorMock,
        $dispatcherMock,
        $hookManagerMock
    );

    echo "Testing upgrade of module $moduleName with identical version (1.0.0)...\n";
    
    // The bug: upgrade() returns false if the version is the same because upgradeMigration() 
    // calls LegacyModule::getUpgradeStatus() which returns false when versions match.
    // After the fix, upgradeMigration() returns true regardless of the version status.
    $result = $manager->upgrade($moduleName);

    echo "Result of upgrade(): " . ($result ? 'TRUE' : 'FALSE') . "\n";

    if ($result === true) {
        echo "SUCCESS: Module upgrade succeeded even with the same version.\n";
        exit(0);
    } else {
        echo "FAILURE: Module upgrade failed because versions are identical (bug present).\n";
        exit(1);
    }
} catch (\Throwable $t) {
    echo "ERROR: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
    exit(1);
}
