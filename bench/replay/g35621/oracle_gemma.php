<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #35621, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Since we are in a CLI environment where the Symfony autoloader might not 
 * be fully configured for all src/ classes, and we cannot use 'namespace' 
 * after 'require', we use class_alias to satisfy type-hints in the constructor.
 */

class MockDataProvider {
    public function setActionUrls($collection) {
        return $collection;
    }
}
class_alias('MockDataProvider', 'PrestaShop\PrestaShop\Core\Module\AdminModuleDataProvider');

class MockCache {
    public $deletedKeys = [];
    public function contains($key) {
        return true;
    }
    public function delete($key) {
        $this->deletedKeys[] = $key;
        return true;
    }
    public function deleteAll() {
        return true;
    }
    public function save($key, $value) {
        return true;
    }
}
// Alias to the interface expected by ModuleRepository constructor
class_alias('MockCache', 'PrestaShop\PrestaShop\Core\Module\Cache\CacheProviderInterface');

use PrestaShop\PrestaShop\Core\Module\ModuleRepository;

try {
    // 1. Setup: Ensure we have at least 2 shops to test "All stores" context
    // Shop 1 exists by default. Create Shop 2.
    $shop2 = new Shop();
    $shop2->id_shop_group = 1;
    $shop2->name = 'Test Shop 2';
    $shop2->active = 1;
    $shop2->id_category = 2; // Required field to avoid validation error
    $shop2->add();

    $shops = Shop::getShops(true, null, true);
    $shopCount = count($shops);
    echo "Nombre de boutiques détectées : $shopCount\n";

    // 2. Instantiate dependencies
    $mockCache = new MockCache();
    $dummyDataProvider = new MockDataProvider();

    // 3. Instantiate ModuleRepository
    $modulePath = '/var/www/html/modules';
    $repository = new ModuleRepository($mockCache, $modulePath, $dummyDataProvider);

    // 4. Execute the code that triggers the bug
    $moduleName = 'ps_testmodule';
    echo "Appel de clearCache pour '$moduleName' avec allShops = true...\n";
    $repository->clearCache($moduleName, true);

    // 5. Analysis
    // Before fix: clearCache returns true inside the loop, deleting only shop-specific keys.
    // After fix: clearCache deletes shop-specific keys AND the general module key.
    $deletedCount = count($mockCache->deletedKeys);
    $expectedCount = $shopCount + 1; // Shop keys + 1 general key

    echo "Clés de cache supprimées : $deletedCount\n";
    echo "Clés attendues (boutiques + générale) : $expectedCount\n";

    if ($deletedCount < $expectedCount) {
        echo "ÉCHEC : La clé de cache générale n'a pas été supprimée (bug présent).\n";
        exit(1);
    }

    echo "SUCCÈS : Toutes les clés de cache ont été supprimées.\n";
    exit(0);

} catch (\Throwable $t) {
    echo "Erreur fatale : " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
    exit(1);
}
