<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #28445, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Core\Module\ModuleRepository;
use PrestaShop\PrestaShop\Core\Module\EventSubscriber;
use PrestaShop\PrestaShop\Core\Module\Module;
use PrestaShop\PrestaShop\Core\Module\ModuleDataProvider;
use PrestaShop\PrestaShop\Core\Module\AdminModuleDataProvider;
use PrestaShop\PrestaShop\Core\Module\CacheProvider;
use PrestaShop\PrestaShop\Core\Module\HookManager;
use PrestaShopBundle\Event\ModuleManagementEvent;

// 1. Setup Multishop
Configuration::updateValue('PS_MULTISHOP_ACTIVE', 1);

// Ensure a ShopGroup exists
$group = new ShopGroup();
$group->name = 'Test Group';
$group->physical = 1;
$group->add();
$groupId = $group->id;

// Ensure shops exist with all required fields
$shopIds = [1, 2];
foreach ($shopIds as $id) {
    $shop = new Shop($id);
    if (!Validate::isLoadedObject($shop)) {
        $shop = new Shop();
        $shop->id = $id;
        $shop->name = 'Shop ' . $id;
        $shop->active = 1;
        $shop->id_category = 2; // Root category from demo data
        $shop->id_shop_group = $groupId;
        $shop->add();
    }
}

// 2. Setup Dummy Module
$moduleName = 'testmodule';
// Use legacy Module class for DB insertion
$mLegacy = Module::getInstanceByName($moduleName);
if (!$mLegacy) {
    $mLegacy = new Module();
    $mLegacy->name = $moduleName;
    $mLegacy->active = 1;
    $mLegacy->version = '1.0';
    $mLegacy->add();
}

// Create physical files so getModulePath doesn't return null
$modulePath = _PS_MODULE_DIR_ . $moduleName;
if (!is_dir($modulePath)) {
    mkdir($modulePath, 0777, true);
}
file_put_contents($modulePath . '/' . $moduleName . '.php', '<?php');

// 3. Mock CacheProvider to track deletions
class MockCacheProvider implements CacheProvider {
    public $data = [];
    public function contains($key) { return isset($this->data[$key]); }
    public function delete($key) { 
        if (isset($this->data[$key])) {
            unset($this->data[$key]); 
            return true; 
        }
        return false; 
    }
    public function save($key, $value) { $this->data[$key] = $value; }
    public function fetch($key) { return $this->data[$key] ?? null; }
    public function deleteAll() { $this->data = []; return true; }
}

// 4. Instantiate ModuleRepository with mocks
$refDataProvider = new ReflectionClass(ModuleDataProvider::class);
$moduleDataProvider = $refDataProvider->newInstanceWithoutConstructor();

$refAdminProvider = new ReflectionClass(AdminModuleDataProvider::class);
$adminModuleDataProvider = $refAdminProvider->newInstanceWithoutConstructor();

$refHookManager = new ReflectionClass(HookManager::class);
$hookManager = $refHookManager->newInstanceWithoutConstructor();

$cacheProvider = new MockCacheProvider();

$repository = new ModuleRepository(
    $moduleDataProvider,
    $adminModuleDataProvider,
    $cacheProvider,
    $hookManager,
    _PS_MODULE_DIR_
);

// 5. Populate Cache for both shops
// Context Shop 1
Shop::setContext(Shop::CONTEXT_SHOP, 1);
$repository->getModule($moduleName); 
$keyShop1 = $moduleName . '1';

// Context Shop 2
Shop::setContext(Shop::CONTEXT_SHOP, 2);
$repository->getModule($moduleName);
$keyShop2 = $moduleName . '2';

echo "Cache before: Shop1=" . ($cacheProvider->contains($keyShop1) ? 'YES' : 'NO') . ", Shop2=" . ($cacheProvider->contains($keyShop2) ? 'YES' : 'NO') . "\n";

// 6. Trigger the event in Shop 2 context
// The bug: disabling in Shop 2 only clears Shop 2 cache, leaving Shop 1 cache stale.
$moduleDto = new Module(['name' => $moduleName], [], []);
$event = new ModuleManagementEvent($moduleDto);

class MockCacheClearer { public function clear() {} }
$cacheClearer = new MockCacheClearer();

$subscriber = new EventSubscriber($repository, $cacheClearer);
$subscriber->onModuleStateChanged($event);

echo "Cache after: Shop1=" . ($cacheProvider->contains($keyShop1) ? 'YES' : 'NO') . ", Shop2=" . ($cacheProvider->contains($keyShop2) ? 'YES' : 'NO') . "\n";

// 7. Verification
if ($cacheProvider->contains($keyShop1)) {
    echo "FAILURE: Cache for Shop 1 was not cleared.\n";
    exit(1);
}

echo "SUCCESS: Cache for all shops cleared.\n";
exit(0);
