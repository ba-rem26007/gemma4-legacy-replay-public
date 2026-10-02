<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #36505, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Manual definition of RedirectType to avoid file path issues in CLI.
 * This class only contains constants used for comparison.
 */
if (!class_exists('PrestaShop\PrestaShopBundle\Model\Product\RedirectType')) {
    eval('namespace PrestaShop\PrestaShopBundle\Model\Product { 
        class RedirectType { 
            const TYPE_PRODUCT_PERMANENT = 301; 
            const TYPE_PRODUCT_TEMPORARY = 302; 
            const TYPE_CATEGORY_PERMANENT = 1; 
        } 
    }');
}

/**
 * Load AdminModelAdapter.
 * We try multiple paths to ensure the class is loaded regardless of the environment's CWD.
 */
$adapterClass = 'PrestaShop\PrestaShopBundle\Model\Product\AdminModelAdapter';
if (!class_exists($adapterClass)) {
    $paths = [
        'src/PrestaShopBundle/Model/Product/AdminModelAdapter.php',
        '/var/www/html/src/PrestaShopBundle/Model/Product/AdminModelAdapter.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
}

if (!class_exists($adapterClass)) {
    echo "Critical Error: Class $adapterClass could not be loaded.\n";
    exit(1);
}

use PrestaShop\PrestaShopBundle\Model\Product\AdminModelAdapter;
use PrestaShop\PrestaShopBundle\Model\Product\RedirectType;

// Setup context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

// Use Reflection to bypass the complex constructor of AdminModelAdapter
try {
    $reflection = new ReflectionClass($adapterClass);
    $adapter = $reflection->newInstanceWithoutConstructor();
} catch (\ReflectionException $e) {
    echo "Reflection Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Initialize required properties used in getModelData
$adapter->translatableKeys = [];

// Prepare form data that triggers the bug.
// The bug occurs when 'id_type_redirected' is passed as a numeric ID (as it is in the new product page)
// but the old code expects an array ['data' => [id]].
$formData = [
    'id_product' => 1,
    'step1' => [
        'type_product' => 1,
    ],
    'step2' => [],
    'step3' => [],
    'step4' => [],
    'step5' => [],
    'step6' => [
        'redirect_type' => RedirectType::TYPE_PRODUCT_PERMANENT,
        'id_type_redirected' => 2, // Numeric ID of the target product
    ],
];

echo "Testing Product Redirection storage logic...\n";
echo "Input redirect_type: " . RedirectType::TYPE_PRODUCT_PERMANENT . "\n";
echo "Input id_type_redirected: 2\n";

try {
    $result = $adapter->getModelData($formData);
    $observedRedirectType = $result['redirect_type'];
    echo "Observed redirect_type after getModelData: $observedRedirectType\n";

    // Before fix: it falls back to TYPE_CATEGORY_PERMANENT because it checks for ['data'][0]
    // After fix: it accepts numeric values.
    if ($observedRedirectType === RedirectType::TYPE_PRODUCT_PERMANENT) {
        echo "SUCCESS: Redirect type preserved.\n";
        exit(0);
    } else {
        echo "FAILURE: Redirect type was incorrectly changed to " . $observedRedirectType . "\n";
        exit(1);
    }
} catch (\Throwable $t) {
    echo "ERROR: " . $t->getMessage() . "\n";
    exit(1);
}
