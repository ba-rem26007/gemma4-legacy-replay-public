<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #28267, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Core\Addon\Theme\Theme;

/**
 * Test for Ticket: When changing a theme, a "yaml" cache folder is created in wrong Prestashop folders.
 * 
 * The bug occurs because the Theme class was using the legacy Configuration class (via 'use Configuration;')
 * instead of the Adapter\Configuration class. The legacy class does not have a get() method on its instance,
 * leading to a failure in retrieving the cache directory, which causes the YamlParser to create 
 * the 'yaml' cache folder in the current working directory (root or module folder).
 */

// 1. Setup: Create a dummy parent and child theme structure to trigger the YamlParser
$parentThemeName = 'parent_theme_test';
$childThemeName = 'child_theme_test';
$parentThemeDir = _PS_ALL_THEMES_DIR_ . $parentThemeName;
$childThemeDir = _PS_ALL_THEMES_DIR_ . $childThemeName;

if (!is_dir($parentThemeDir . '/config')) {
    mkdir($parentThemeDir . '/config', 0777, true);
}
if (!is_dir($childThemeDir . '/config')) {
    mkdir($childThemeDir . '/config', 0777, true);
}

file_put_contents($parentThemeDir . '/config/theme.yml', "name: $parentThemeName\nversion: 1.0.0");
file_put_contents($childThemeDir . '/config/theme.yml', "name: $childThemeName\nparent: $parentThemeName");

// 2. Cleanup: Ensure no 'yaml' folder exists in the root before starting the test
$rootYamlDir = getcwd() . '/yaml';
if (is_dir($rootYamlDir)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootYamlDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }
    rmdir($rootYamlDir);
}

$attributes = [
    'name' => $childThemeName,
    'parent' => $parentThemeName,
    'directory' => $childThemeDir . '/',
];

try {
    echo "Instantiating Theme with child theme attributes...\n";
    // This triggers the constructor: (new Configuration())->get('_PS_CACHE_DIR_')
    $theme = new Theme($attributes);
    echo "Theme instantiated successfully.\n";
} catch (\Throwable $t) {
    echo "Caught exception/error: " . $t->getMessage() . "\n";
    // If the code crashes (e.g. ClassNotFound or MethodNotFound), it's a failure (Before fix)
    exit(1);
}

// 3. Verification: Check if the 'yaml' folder was created in the current working directory
if (is_dir($rootYamlDir)) {
    echo "FAIL: 'yaml' folder created in root directory: $rootYamlDir\n";
    exit(1);
}

echo "SUCCESS: No 'yaml' folder created in root directory.\n";
exit(0);
