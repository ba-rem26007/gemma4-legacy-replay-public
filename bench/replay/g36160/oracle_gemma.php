<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #36160, validé pre/post automatiquement
require 'config/config.inc.php';

// Ensure Composer autoloader is loaded for Symfony components (like Finder)
if (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
}

/**
 * Manual loading of the required classes from src/ to avoid autoloader issues in CLI
 * The order is important due to dependencies.
 */
$filesToLoad = [
    'src/PrestaShopBundle/Translation/TranslatorInterface.php',
    'src/PrestaShopBundle/Translation/BaseTranslatorComponent.php',
    'src/PrestaShopBundle/Translation/ModuleRepository.php',
    'src/PrestaShopBundle/Translation/TranslatorLanguageLoader.php',
];

foreach ($filesToLoad as $file) {
    $path = _PS_ROOT_DIR_ . '/' . $file;
    if (file_exists($path)) {
        require_once $path;
    }
}

use PrestaShopBundle\Translation\TranslatorLanguageLoader;
use PrestaShopBundle\Translation\ModuleRepository;
use PrestaShopBundle\Translation\BaseTranslatorComponent;

/**
 * Mock of ModuleRepository to satisfy the type hint in TranslatorLanguageLoader
 */
class MockModuleRepository extends ModuleRepository
{
    public function __construct() {}
    public function getActiveModulesPaths()
    {
        return [];
    }
}

/**
 * Mock of BaseTranslatorComponent to track which files are actually loaded
 */
class TestTranslator extends BaseTranslatorComponent
{
    public $loadedFiles = [];

    public function __construct() {}

    public function isLanguageLoaded($locale)
    {
        return false;
    }

    public function addResource($format, $file, $locale, $domain)
    {
        // $file is a Symfony\Component\Finder\SplFileInfo object
        if ($file instanceof \Symfony\Component\Finder\SplFileInfo) {
            $this->loadedFiles[] = $file->getPathname();
        } else {
            $this->loadedFiles[] = (string)$file;
        }
    }
}

try {
    // 1. Setup: Create a real translation file in a temporary location
    $realFile = '/tmp/ps_test_symlink_trans.fr-FR.xlf';
    file_put_contents($realFile, '<?xml version="1.0" encoding="UTF-8"?><xliff version="1.0"><file source="test"><body translation-unit="test"><trans-unit id="test"><target>Test Translation</target></trans-unit></body></file></xliff>');

    // 2. Setup: Create a symlink in the core translations directory
    // TranslatorLanguageLoader::getTranslationResourcesDirectories usually includes this path
    $transDir = _PS_ROOT_DIR_ . '/app/Resources/translations';
    if (!is_dir($transDir)) {
        mkdir($transDir, 0777, true);
    }
    
    $symlinkFile = $transDir . '/test_domain.fr-FR.xlf';
    if (file_exists($symlinkFile)) {
        unlink($symlinkFile);
    }
    
    if (!symlink($realFile, $symlinkFile)) {
        throw new \Exception("Failed to create symlink from $realFile to $symlinkFile");
    }

    // 3. Execution: Instantiate the loader and the translator
    $moduleRepo = new MockModuleRepository();
    $loader = new TranslatorLanguageLoader($moduleRepo);
    $translator = new TestTranslator();

    // Use 'fr-FR' to match the filename pattern *.fr-FR.xlf
    $locale = 'fr-FR';
    $loader->loadLanguage($translator, $locale);

    // 4. Verification: Check if the symlinked file was found and loaded
    $found = false;
    foreach ($translator->loadedFiles as $file) {
        if (strpos($file, 'test_domain.fr-FR.xlf') !== false) {
            $found = true;
            break;
        }
    }

    echo "Symlink file: $symlinkFile\n";
    echo "Files loaded by translator: " . count($translator->loadedFiles) . "\n";
    echo "Symlink detected: " . ($found ? 'YES' : 'NO') . "\n";

    // Cleanup
    if (file_exists($symlinkFile)) unlink($symlinkFile);
    if (file_exists($realFile)) unlink($realFile);

    exit($found ? 0 : 1);

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
