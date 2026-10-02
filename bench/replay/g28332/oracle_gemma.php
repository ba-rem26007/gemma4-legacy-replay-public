<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #28332, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\Module\AdminModuleDataProvider;

/**
 * The test verifies the order of actions in the AdminModuleDataProvider.
 * The fix changes the order of the $moduleActions protected property.
 * 
 * We use the string values of the constants to avoid "Undefined constant" errors 
 * in the CLI environment where the Module class might not be fully initialized.
 */

try {
    // We use Reflection to instantiate the class without calling the constructor
    // because the constructor requires several Symfony-based providers.
    $reflectionClass = new ReflectionClass(AdminModuleDataProvider::class);
    $provider = $reflectionClass->newInstanceWithoutConstructor();

    // Access the protected property $moduleActions
    $property = $reflectionClass->getProperty('moduleActions');
    $property->setAccessible(true);
    $observedActions = $property->getValue($provider);

    // Expected order after the fix (based on the provided diff)
    // Module::ACTION_INSTALL = 'install'
    // Module::ACTION_ENABLE = 'enable'
    // Module::ACTION_CONFIGURE = 'configure'
    // Module::ACTION_UPGRADE = 'upgrade'
    // Module::ACTION_ENABLE_MOBILE = 'enable_mobile'
    // Module::ACTION_DISABLE_MOBILE = 'disable_mobile'
    // Module::ACTION_DISABLE = 'disable'
    // Module::ACTION_RESET = 'reset'
    // Module::ACTION_UNINSTALL = 'uninstall'
    $expectedActions = [
        'install',
        'enable',
        'configure',
        'upgrade',
        'enable_mobile',
        'disable_mobile',
        'disable',
        'reset',
        'uninstall',
    ];

    echo "Observed actions order: " . implode(', ', $observedActions) . "\n";
    echo "Expected actions order: " . implode(', ', $expectedActions) . "\n";

    if ($observedActions === $expectedActions) {
        echo "Success: The actions order is correct.\n";
        exit(0);
    } else {
        echo "Failure: The actions order does not match the expected sequence.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
