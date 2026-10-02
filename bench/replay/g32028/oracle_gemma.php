<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #32028, validé pre/post automatiquement
require 'config/config.inc.php';

// 1. Setup Multistore environment
Configuration::updateValue('PS_MULTISHOP_FEATURE_ACTIVE', 1);

// Ensure we have at least two shops to trigger the bug (count($id_shops) > 1)
$s1 = new Shop(1);
if (!Validate::isLoadedObject($s1)) {
    $g1 = new ShopGroup();
    $g1->name = 'Group 1';
    $g1->physical_address = 'Address 1';
    $g1->add();
    
    $s1 = new Shop();
    $s1->id_shop_group = $g1->id;
    $s1->id_category = 2;
    $s1->name = 'Shop 1';
    $s1->active = 1;
    $s1->add();
}

$s2 = new Shop(2);
if (!Validate::isLoadedObject($s2)) {
    $g2 = new ShopGroup();
    $g2->name = 'Group 2';
    $g2->physical_address = 'Address 2';
    $g2->add();
    
    $s2 = new Shop();
    $s2->id_shop_group = $g2->id;
    $s2->id_category = 2;
    $s2->name = 'Shop 2';
    $s2->active = 1;
    $s2->add();
}

// Set context to "All shops"
Shop::setContext(Shop::CONTEXT_ALL);
$context = new Context();
$id_shops = $context->getContextListShopID();
echo "Shops in context: " . count($id_shops) . " (" . implode(',', $id_shops) . ")\n";

if (count($id_shops) <= 1) {
    echo "Error: Test requires at least 2 shops in context to trigger the bug.\n";
    exit(1);
}

// 2. Setup Module data
$moduleName = 'ps_linklist';
$m = new Module();
$m->name = $moduleName;
$m->active = 1;
$m->version = '1.0.0';
$m->add();

// Link module to both shops in the module_shop table
Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'module_shop` WHERE `id_module` = ' . (int)$m->id);
Db::getInstance()->execute('
    INSERT INTO `' . _DB_PREFIX_ . 'module_shop` (`id_module`, `id_shop`, `enable_device`) 
    VALUES (' . (int)$m->id . ', ' . (int)$s1->id . ', 7), 
           (' . (int)$m->id . ', ' . (int)$s2->id . ', 7)
');

// 3. Instantiate ModuleDataProvider with dummy dependencies
$logger = new class implements \Psr\Log\LoggerInterface {
    public function emergency($m, array $c = []){}
    public function alert($m, array $c = []){}
    public function critical($m, array $c = []){}
    public function error($m, array $c = []){}
    public function warning($m, array $c = []){}
    public function notice($m, array $c = []){}
    public function info($m, array $c = []){}
    public function debug($m, array $c = []){}
};

$translator = new class implements \PrestaShop\PrestaShop\Adapter\Translator\TranslatorInterface {
    public function trans($id, $locale = null, $domain = null) { return $id; }
};

$dataProvider = new \PrestaShop\PrestaShop\Adapter\Module\ModuleDataProvider($logger, $translator);

// 4. Execute and Verify
try {
    $installedModules = $dataProvider->getInstalled();
    
    if (!isset($installedModules[$moduleName])) {
        echo "Error: Module $moduleName not found in installed list.\n";
        exit(1);
    }

    $moduleData = $installedModules[$moduleName];
    $hasActiveKey = array_key_exists('active', $moduleData);
    
    echo "Module $moduleName 'active' key present: " . ($hasActiveKey ? 'YES' : 'NO') . "\n";
    
    // The bug: when count($id_shops) > 1, the 'active' key is missing because the 
    // LEFT JOIN is skipped in the old code (if count($id_shops) === 1).
    exit($hasActiveKey ? 0 : 1);

} catch (\Throwable $t) {
    echo "Exception caught: " . $t->getMessage() . "\n";
    exit(1);
}
