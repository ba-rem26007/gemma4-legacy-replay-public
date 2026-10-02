<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29069, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Core\Addon\Theme\ThemeManager;
use PrestaShop\PrestaShop\Core\Addon\Theme\ThemeRepository;
use PrestaShop\PrestaShop\Core\Addon\Theme\ThemeValidator;
use PrestaShop\PrestaShop\Core\Addon\Theme\Theme;
use PrestaShop\PrestaShop\Core\Module\HookConfigurator;
use PrestaShop\PrestaShop\Core\Image\ImageTypeRepository;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

// Ensure constant is defined
if (!defined('_PS_THEMES_DIR_')) {
    define('_PS_THEMES_DIR_', _PS_ROOT_DIR_ . 'themes/');
}

/**
 * Mock Theme class to simulate a theme with missing configuration keys.
 */
class MockTheme extends Theme {
    public function __construct() {}
    public function get($attr = null, $default = null) {
        // The bug occurs when this returns null instead of the default array
        // because the ThemeManager didn't provide a default value in the call.
        return $default;
    }
    public function getModulesToEnable() {
        return [];
    }
    public function onEnable() {}
    public function onUninstall() {}
    public function getDirectory() {
        return _PS_THEMES_DIR_ . 'bug_theme';
    }
}

/**
 * Mock ThemeRepository to return our MockTheme.
 */
class MockThemeRepository extends ThemeRepository {
    public function __construct() {}
    public function getInstanceByName($name) {
        return new MockTheme();
    }
}

try {
    // 1. Mock Dependencies
    $configuration = new class implements \PrestaShop\PrestaShop\Core\ConfigurationInterface {
        public function get($key, $default = null) { return $default; }
        public function set($key, $value) { return true; }
        public function delete($key) { return true; }
        public function update($key, $value) { return true; }
    };

    $translator = new class implements \Symfony\Component\Translation\TranslatorInterface {
        public function trans($id, $parameters = [], $domain = null, $locale = null) { return $id; }
        public function transChoice($id, $number, $parameters = [], $domain = null, $locale = null) { return $id; }
        public function getCatalogue($locale) { return null; }
        public function setLocale($locale) {}
        public function getLocale() { return 'fr_FR'; }
    };

    $themeValidator = new class extends ThemeValidator {
        public function __construct() {}
    };
    $hookConfigurator = new class extends HookConfigurator {
        public function __construct() {}
    };
    $imageTypeRepository = new class extends ImageTypeRepository {
        public function __construct() {}
    };

    $shop = new Shop();
    $employee = new Employee(1);
    $filesystem = new Filesystem();
    $finder = new Finder();
    $themeRepository = new MockThemeRepository();

    // 2. Instantiate ThemeManager
    $themeManager = new ThemeManager(
        $shop,
        $configuration,
        $themeValidator,
        $translator,
        $employee,
        $filesystem,
        $finder,
        $hookConfigurator,
        $themeRepository,
        $imageTypeRepository
    );

    echo "Attempting to enable theme with missing hook/image settings...\n";
    
    // This call triggers the bug:
    // Before fix: ThemeManager calls $theme->get('global_settings.hooks.modules_to_hook') 
    // without a second argument. MockTheme::get returns null. 
    // doHookModules(null) is called -> TypeError.
    // After fix: ThemeManager calls $theme->get('...', []), MockTheme::get returns [].
    // doHookModules([]) is called -> Success.
    $themeManager->enable('bug_theme');

    echo "Success: Theme enabled without error.\n";
    exit(0);

} catch (\Throwable $t) {
    echo "Caught error: " . get_class($t) . "\n";
    echo "Message: " . $t->getMessage() . "\n";
    
    // If it's a TypeError, the bug is still present
    exit(1);
}
