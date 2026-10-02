<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #34370, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Mock Smarty to intercept the rendering process.
 * We throw an exception in fetch() to stop execution before the 'exit' call 
 * inside displayMaintenancePage(), allowing us to inspect the controller state.
 */
class MockSmarty {
    public function assign($params, $name = null) {
        return true;
    }
    public function fetch($template) {
        throw new Exception('StopBeforeExit');
    }
    public function getTemplateDir() {
        return '';
    }
}

/**
 * Test controller to expose the protected displayMaintenancePage method.
 */
class TestFrontController extends FrontController {
    public function triggerMaintenance() {
        $this->displayMaintenancePage();
    }

    // Prevent automatic redirections or exits during construction/init
    public function canonicalRedirection($url = '') {}
    public function geolocationManagement($defaultCountry) {}
    public function init() {} 
}

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

// Replace Smarty with our mock to prevent the script from exiting
$context->smarty = new MockSmarty();

// Ensure shop is in maintenance mode
Configuration::updateValue('PS_MAINTENANCE', 1);

$controller = new TestFrontController();

try {
    // This call executes the real displayMaintenancePage()
    // It will call setMedia() (if fixed), then registerStylesheet('theme-error'),
    // then smarty->fetch() which throws our exception.
    $controller->triggerMaintenance();
} catch (Exception $e) {
    if ($e->getMessage() !== 'StopBeforeExit') {
        echo "Unexpected exception: " . $e->getMessage() . "\n";
        exit(1);
    }
}

$stylesheets = $controller->getStylesheets();

// PrestaShop often has 'external' and 'inline' keys in the stylesheets array
// to categorize assets. We remove them to count only the actual registered files.
unset($stylesheets['external'], $stylesheets['inline']);

$filteredStyles = array_keys($stylesheets);
$count = count($filteredStyles);

echo "Registered stylesheets (excluding external/inline): $count\n";
foreach ($filteredStyles as $id) {
    echo " - $id\n";
}

/**
 * DIAGNOSTIC:
 * Before fix: displayMaintenancePage() only calls registerStylesheet('theme-error', ...).
 *             The filtered list contains only ['theme-error']. Count = 1.
 * After fix: displayMaintenancePage() calls setMedia() first.
 *            setMedia() registers the theme's core CSS (e.g., 'theme-css' or similar).
 *            Then 'theme-error' is added.
 *            The filtered list contains 'theme-error' AND at least one theme asset. Count > 1.
 */
exit($count > 1 ? 0 : 1);
