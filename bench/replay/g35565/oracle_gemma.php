<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #35565, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

/**
 * Helper to extract protected 'list' property from Asset Managers
 */
function getAssetList($manager) {
    $reflection = new ReflectionClass($manager);
    $property = $reflection->getProperty('list');
    $property->setAccessible(true);
    return $property->getValue($manager);
}

try {
    // We use 'index.php' as a dummy file because getFullPath() checks if the file exists on disk.
    $dummyFile = 'index.php';
    $version = '1.2.3';
    $assetId = 'test-asset';

    echo "Testing JavascriptManager...\n";
    // AbstractAssetManager constructor expects (array $directories, Context $context)
    $jsManager = new JavascriptManager([_PS_ROOT_DIR_], $context);
    $jsManager->register(
        $assetId,
        $dummyFile,
        'bottom',
        0,
        false,
        null,
        'local',
        $version
    );

    $jsList = getAssetList($jsManager);
    $jsAsset = $jsList['bottom']['external'][$assetId] ?? null;

    if (!$jsAsset) {
        echo "Error: JS asset not registered. Check if $dummyFile exists in " . _PS_ROOT_DIR_ . "\n";
        exit(1);
    }

    $jsPath = $jsAsset['path'];
    $jsUri = $jsAsset['uri'];
    echo "JS Path: $jsPath\n";
    echo "JS URI: $jsUri\n";

    // BUG: The 'path' contains the version query string, which breaks CCC (file_get_contents fails)
    $jsBuggy = (strpos($jsPath, '?') !== false);

    echo "\nTesting StylesheetManager...\n";
    // AbstractAssetManager constructor expects (array $directories, Context $context)
    $cssManager = new StylesheetManager([_PS_ROOT_DIR_], $context);
    $cssManager->register(
        $assetId,
        $dummyFile,
        'all',
        0,
        false,
        'local',
        true,
        $version
    );

    $cssList = getAssetList($cssManager);
    $cssAsset = $cssList['external'][$assetId] ?? null;

    if (!$cssAsset) {
        echo "Error: CSS asset not registered. Check if $dummyFile exists in " . _PS_ROOT_DIR_ . "\n";
        exit(1);
    }

    $cssPath = $cssAsset['path'];
    $cssUri = $cssAsset['uri'];
    echo "CSS Path: $cssPath\n";
    echo "CSS URI: $cssUri\n";

    // BUG: The 'path' contains the version query string
    $cssBuggy = (strpos($cssPath, '?') !== false);

    if ($jsBuggy || $cssBuggy) {
        echo "FAIL: Version string found in 'path'. CCC will be broken.\n";
        exit(1);
    }

    // Verify that the version is still present in the URI (so it's not just removed everywhere)
    if (strpos($jsUri, $version) === false || strpos($cssUri, $version) === false) {
        echo "FAIL: Version string missing from URI.\n";
        exit(1);
    }

    echo "SUCCESS: Paths are clean, URIs are versioned.\n";
    exit(0);

} catch (\Throwable $t) {
    echo "Exception: " . $t->getMessage() . "\n";
    exit(1);
}
