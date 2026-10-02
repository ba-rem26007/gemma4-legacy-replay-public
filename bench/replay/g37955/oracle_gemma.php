<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37955, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Test for Ticket: PrestaShop don't display Title of product, category meta title, meta description
 * when shop is under maintenance mode but admin is allowed to see FO.
 * 
 * The fix introduces Tools::isAllowedToBypassMaintenance() which is now used by Meta::getMetaTags.
 * This method improves the IP check by adding array_map('trim', ...), which prevents 
 * failures when the PS_MAINTENANCE_IP configuration contains spaces.
 */

// 1. Setup environment to trigger the bug
// Shop is disabled (Maintenance mode)
Configuration::updateValue('PS_SHOP_ENABLE', 0);

// We use a specific trick to ensure the test fails on the old code and passes on the new code.
// The old code used: explode(',', Configuration::get('PS_MAINTENANCE_IP'))
// The new code uses: array_map('trim', explode(',', Configuration::get('PS_MAINTENANCE_IP')))
// By adding a space before the IP, the old code will fail to match the IP, 
// while the new code will succeed.
$ip = '127.0.0.1';
Configuration::updateValue('PS_MAINTENANCE_IP', ' ' . $ip);
$_SERVER['REMOTE_ADDR'] = $ip;

// 2. Simulate a request for a specific product
$idProduct = 1;
$idLang = 1;
$_GET['id_product'] = $idProduct;

// Ensure product 1 exists (demo data)
$product = new Product($idProduct, false, $idLang);
if (!Validate::isLoadedObject($product)) {
    echo "Product 1 not found in demo data.\n";
    exit(1);
}

echo "Shop status: Maintenance mode (PS_SHOP_ENABLE=0)\n";
echo "Remote IP: $ip\n";
echo "Maintenance IP Config: ' " . Configuration::get('PS_MAINTENANCE_IP') . "' (with leading space)\n";
echo "Target Page: Product $idProduct\n";

try {
    // Call the method touched by the fix.
    // Before the fix: Meta::getMetaTags calls IpUtils::checkIp with [' 127.0.0.1'], which returns FALSE.
    // After the fix: Meta::getMetaTags calls Tools::isAllowedToBypassMaintenance(), 
    // which trims the IP list to ['127.0.0.1'], and IpUtils::checkIp returns TRUE.
    $observedMetas = Meta::getMetaTags($idLang, 'product');
    
    // Get what the metas SHOULD be (Product metas)
    $expectedMetas = Meta::getProductMetas($idProduct, $idLang, 'product');

    echo "Observed Meta Title: " . ($observedMetas['meta_title'] ?? 'NULL') . "\n";
    echo "Expected Meta Title: " . ($expectedMetas['meta_title'] ?? 'NULL') . "\n";

    if ($observedMetas === $expectedMetas) {
        echo "SUCCESS: Meta tags are correctly retrieved. The bypass logic (including trim) is working.\n";
        exit(0);
    } else {
        echo "FAILURE: Meta tags were not retrieved (likely fell back to Home metas because of the untrimmed IP).\n";
        exit(1);
    }
} catch (\Throwable $t) {
    echo "ERROR: An exception occurred: " . $t->getMessage() . "\n";
    exit(1);
}
