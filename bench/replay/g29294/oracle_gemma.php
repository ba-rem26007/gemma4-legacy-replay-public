<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29294, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Core\Addon\Theme\Theme;

/**
 * Mock Controller to simulate a Module Front Controller.
 * Module controllers have an empty php_self and use getPageName() for identification.
 */
class MockModuleFrontController extends FrontController
{
    public function getPageName()
    {
        return 'layouttest';
    }

    /**
     * Wrapper to access the protected method getTemplateVarPage
     */
    public function testGetTemplateVarPage()
    {
        return $this->getTemplateVarPage();
    }
}

try {
    // 1. Setup Context
    $context = Context::getContext();
    $context->shop = new Shop(1);
    $context->language = new Language(1);
    $context->currency = new Currency(1);
    $context->customer = new Customer(1);
    
    // Cart is required by some FrontController methods
    $cart = new Cart();
    $cart->id_currency = 1;
    $cart->id_lang = 1;
    $context->cart = $cart;

    // 2. Setup Theme
    // The Theme class uses an internal attributes system. 
    // We provide the basic required keys to avoid warnings.
    $themeAttributes = [
        'name' => 'classic',
        'directory' => 'classic',
        'theme_settings' => [
            'default_layout' => 'layout-full-width',
            'layouts' => [],
        ],
    ];
    
    $theme = new Theme($themeAttributes);
    
    // Use the public method to set the layout for our specific page.
    // This ensures the internal attribute storage is updated correctly.
    $theme->setPageLayouts(['layouttest' => 'layout-left-column']);
    
    // Link the theme to the shop in the context
    $context->shop->theme = $theme;

    // 3. Instantiate the Controller
    $controller = new MockModuleFrontController();
    
    // Simulate a module controller: php_self is empty, identification relies on getPageName()
    $controller->php_self = '';

    // 4. Execute the code touched by the fix
    $pageVars = $controller->testGetTemplateVarPage();

    echo "Layouts found in page variables:\n";
    if (is_array($pageVars)) {
        foreach ($pageVars as $key => $value) {
            if (strpos($key, 'layout-') === 0) {
                echo "- $key: " . var_export($value, true) . "\n";
            }
        }
    } else {
        echo "pageVars is not an array\n";
    }

    // The bug: Before the fix, getTemplateVarPage used $this->php_self (empty), 
    // which resulted in the default layout (layout-full-width) being set.
    // The fix: It now uses getLayoutName(), which falls back to getPageName() ('layouttest'),
    // correctly retrieving 'layout-left-column'.
    if (isset($pageVars['layout-left-column']) && $pageVars['layout-left-column'] === true) {
        echo "SUCCESS: Correct body class 'layout-left-column' is set.\n";
        exit(0);
    } else {
        echo "FAILURE: Correct body class 'layout-left-column' NOT found.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "ERROR: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
    exit(1);
}
