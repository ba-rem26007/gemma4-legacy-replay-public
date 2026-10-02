<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #38136, validé pre/post automatiquement
require 'config/config.inc.php';

try {
    // Setup: Enable Multishop and set context to "All stores"
    Configuration::updateValue('PS_MULTISHOP_FEATURE_ACTIVE', 1);
    Shop::setContext(Shop::CONTEXT_ALL);

    // Get a valid shop ID from the database to avoid array key issues
    $idShop1 = (int) Db::getInstance()->getValue('SELECT id_shop FROM ' . _DB_PREFIX_ . 'shop ORDER BY id_shop ASC');
    if ($idShop1 <= 0) {
        echo "No shop found in the database.\n";
        exit(1);
    }

    // 1. Set global versions
    // We use null for shop group and shop to target the global configuration
    Configuration::updateValue('PS_CCCJS_VERSION', 100, false, null, null);
    Configuration::updateValue('PS_CCCCSS_VERSION', 100, false, null, null);

    // 2. Set specific versions for Shop 1
    // This simulates a previous "Clear cache" performed while only Shop 1 was selected
    Configuration::updateValue('PS_CCCJS_VERSION', 200, false, null, $idShop1);
    Configuration::updateValue('PS_CCCCSS_VERSION', 200, false, null, $idShop1);

    // Verify initial state
    // Configuration::get with null shop ID returns the global value
    $initialGlobalJs = (int) Configuration::get('PS_CCCJS_VERSION', null, null, null);
    $initialShopJs = (int) Configuration::get('PS_CCCJS_VERSION', null, null, $idShop1);

    echo "Initial state:\n";
    echo "Global JS version: $initialGlobalJs (expected 100)\n";
    echo "Shop $idShop1 JS version: $initialShopJs (expected 200)\n";

    // 3. Trigger the cache clear in "All stores" context
    // The fix should increment the global value AND any existing shop-specific values
    Media::clearCache();

    // 4. Verify results
    $globalJs = (int) Configuration::get('PS_CCCJS_VERSION', null, null, null);
    $globalCss = (int) Configuration::get('PS_CCCCSS_VERSION', null, null, null);
    
    $shop1Js = (int) Configuration::get('PS_CCCJS_VERSION', null, null, $idShop1);
    $shop1Css = (int) Configuration::get('PS_CCCCSS_VERSION', null, null, $idShop1);

    echo "\nAfter Media::clearCache():\n";
    echo "Global JS version: $globalJs (expected 101)\n";
    echo "Global CSS version: $globalCss (expected 101)\n";
    echo "Shop $idShop1 JS version: $shop1Js (expected 201)\n";
    echo "Shop $idShop1 CSS version: $shop1Css (expected 201)\n";

    // The test passes if both the global and the shop-specific values were incremented
    if ($shop1Js === 201 && $shop1Css === 201 && $globalJs === 101 && $globalCss === 101) {
        echo "\nSUCCESS: Both global and shop-specific cache versions were updated.\n";
        exit(0);
    } else {
        echo "\nFAILURE: Cache versions were not updated correctly.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
