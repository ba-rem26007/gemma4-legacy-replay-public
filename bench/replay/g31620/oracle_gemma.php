<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #31620, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Mock container to prevent "Call to undefined method stdClass::get()"
 * FrontController and its parents rely on the Symfony container in CLI.
 */
class MockContainer {
    public function get($id) {
        return null;
    }
    public function has($id) {
        return true;
    }
}

// Setup context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);
$context->container = new MockContainer();

// Simulate request
$_GET['controller'] = 'sitemap';

// Load the controller
require_once 'controllers/front/SitemapController.php';

try {
    $controller = new SitemapControllerCore();
    
    /**
     * We call initContent(). 
     * The logic we are testing (Smarty assignments) occurs BEFORE parent::initContent().
     * parent::initContent() will likely fail in CLI because the MockContainer returns null
     * for services it expects, but the assignments will have already been performed.
     */
    try {
        $controller->initContent();
    } catch (\Throwable $e) {
        // Ignore errors coming from parent::initContent() or setTemplate()
    }
    
    // Retrieve the variables assigned to Smarty
    $vars = $context->smarty->getTemplateVars();
    
    $hasSitemapUrls = isset($vars['sitemapUrls']);
    $hasSitemapDataInUrls = isset($vars['urls']['our_offers']);
    
    echo "Smarty variable 'sitemapUrls' present: " . ($hasSitemapUrls ? 'YES' : 'NO') . "\n";
    echo "Smarty variable 'urls' contains sitemap data: " . ($hasSitemapDataInUrls ? 'YES' : 'NO') . "\n";
    
    // The fix is successful if:
    // 1. 'sitemapUrls' is used to store the sitemap data.
    // 2. 'urls' does not contain the sitemap data (it should contain FrontController's global urls).
    if ($hasSitemapUrls && !$hasSitemapDataInUrls) {
        echo "SUCCESS: Sitemap variables are correctly isolated in 'sitemapUrls'.\n";
        exit(0);
    } else {
        echo "FAILURE: Collision still exists or 'sitemapUrls' is missing.\n";
        exit(1);
    }
} catch (\Throwable $t) {
    echo "FATAL ERROR: " . $t->getMessage() . "\n";
    exit(1);
}
