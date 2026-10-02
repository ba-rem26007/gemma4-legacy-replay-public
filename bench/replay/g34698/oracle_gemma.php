<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #34698, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * We extend FrontController to intercept protected methods that would normally
 * trigger a redirect or an exit() call. If these methods are called in CLI,
 * it means the fix is not working.
 */
class TestFrontController extends FrontController
{
    protected function sslRedirection()
    {
        throw new Exception("sslRedirection called");
    }

    protected function displayMaintenancePage()
    {
        throw new Exception("displayMaintenancePage called");
    }

    protected function canonicalRedirection($canonical_url = '')
    {
        throw new Exception("canonicalRedirection called");
    }

    protected function displayRestrictedCountryPage()
    {
        throw new Exception("displayRestrictedCountryPage called");
    }

    protected function geolocationManagement($defaultCountry)
    {
        throw new Exception("geolocationManagement called");
    }
}

// Setup: Enable conditions that would normally block a FrontController
Configuration::updateValue('PS_SHOP_EXIST', 0); // Maintenance mode ON
Configuration::updateValue('PS_SSL_ENABLED', 1); // SSL Enabled
Configuration::updateValue('PS_SSL_CHEKED', 1);  // SSL Checked

$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

try {
    $controller = new TestFrontController();
    
    // Set php_self to trigger canonicalRedirection logic
    $controller->php_self = 'index';
    
    // The init() method is where all the blocking checks happen
    $controller->init();
    
    echo "Success: FrontController::init() completed without being blocked by CLI checks.\n";
    exit(0);
} catch (\Throwable $e) {
    echo "Failure: " . $e->getMessage() . "\n";
    exit(1);
}
