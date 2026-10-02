<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #27931, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Install\Install;

// Define missing installer constants and classes to avoid fatal errors during instantiation
if (!defined('_PS_INSTALL_LANGS_PATH_')) {
    define('_PS_INSTALL_LANGS_PATH_', _PS_ROOT_DIR_ . '/install/languages/');
}

if (!class_exists('PrestashopInstallerException')) {
    class PrestashopInstallerException extends Exception {}
}

// Setup context
$context = Context::getContext();
$context->shop = new Shop(1);

// Ensure French (id 1) is set as current language
$langFr = new Language(1);
$context->language = $langFr;

// The ModuleConfigReader often requires English ('en') as a fallback language in the database.
// If it's missing, it throws "English language is missing".
$langEn = Language::getLanguageByIso('en');
if (!$langEn) {
    $langEn = new Language();
    $langEn->iso_code = 'en';
    $langEn->name = 'English';
    $langEn->enabled = 1;
    $langEn->add();
}

// Create a dummy module directory with a name different from the internal module name
// This simulates the "disabled" module scenario described in the ticket
$moduleName = 'test_module_real_name';
$dirName = 'test_module_renamed';
$modulePath = _PS_MODULE_DIR_ . $dirName;

if (!is_dir($modulePath)) {
    mkdir($modulePath, 0777, true);
}

// ModuleConfigReader reads the name from config.xml
$configXml = '<?xml version="1.0" encoding="UTF-8"?>
<module>
    <name>' . $moduleName . '</name>
    <version>1.0.0</version>
</module>';
file_put_contents($modulePath . '/config.xml', $configXml);

// Also create a dummy php file to ensure it's recognized as a module
file_put_contents($modulePath . '/' . $moduleName . '.php', '<?php class ' . $moduleName . ' extends Module {}');

try {
    // Instantiate the Install class directly
    $install = new Install();
    
    // Execute the method under test
    $modulesOnDisk = $install->getModulesOnDisk();

    echo "Directory name: $dirName\n";
    echo "Internal module name: $moduleName\n";
    
    // The key in the returned array is the directory name (from Finder)
    $isListed = isset($modulesOnDisk[$dirName]);
    echo "Is the renamed module listed? " . ($isListed ? 'YES' : 'NO') . "\n";

    // Cleanup
    @unlink($modulePath . '/config.xml');
    @unlink($modulePath . '/' . $moduleName . '.php');
    @rmdir($modulePath);

    // The bug is that the module IS listed when the directory name != internal name.
    // If $isListed is true, the bug is still present -> exit 1.
    // If $isListed is false, the fix is working -> exit 0.
    exit($isListed ? 1 : 0);

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    // Cleanup in case of error
    if (is_dir($modulePath)) {
        @unlink($modulePath . '/config.xml');
        @unlink($modulePath . '/' . $moduleName . '.php');
        @rmdir($modulePath);
    }
    exit(1);
}
