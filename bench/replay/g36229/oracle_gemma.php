<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #36229, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Routing\LegacyRouterChecker;
use PrestaShopBundle\Routing\LegacyControllerConstants;
use Symfony\Component\HttpFoundation\Request;
use PrestaShopBundle\Entity\Repository\TabRepository;
use PrestaShop\PrestaShop\Core\Hook\HookDispatcherInterface;
use PrestaShopBundle\Routing\Converter\LegacyParametersConverter;

/**
 * The bug is in LegacyRouterChecker::check().
 * Before the fix:
 * 1. str_ends_with('Controller', $controllerName) had arguments swapped.
 * 2. substr($controllerName, strrpos($controllerName, 'Controller')) returned 'Controller' 
 *    instead of removing it.
 */

// 1. Setup a dummy module directory to satisfy Dispatcher::getControllers()
$moduleName = 'test_module';
$controllerName = 'AdminTestController';
$path = _PS_MODULE_DIR_ . $moduleName . '/controllers/admin/';
if (!is_dir($path)) {
    mkdir($path, 0777, true);
}
// Dispatcher::getControllers looks for files and returns an array with lowercase keys
touch($path . $controllerName . '.php');

// 2. Mock dependencies
// We use anonymous classes to avoid needing the full Symfony Container/EntityManager
$tabRepository = new class extends TabRepository {
    public function __construct() {}
    public function findOneByClassName($name) {
        // Return a dummy object that mimics the Tab entity
        return new class {
            public function getModule() { return 'test_module'; }
        };
    }
};

$hookDispatcher = new class implements HookDispatcherInterface {
    public function dispatchWithParameters($hookName, array $hookParameters = []) {
        return null;
    }
    public function dispatch(object $event, ?string $eventName = null): object {
        return $event;
    }
};

$legacyParametersConverter = new class extends LegacyParametersConverter {
    public function __construct() {}
};

try {
    $checker = new LegacyRouterChecker($tabRepository, $hookDispatcher, $legacyParametersConverter);
    
    // Simulate a request for the module controller
    // The 'controller' parameter is what triggers the logic in LegacyRouterChecker::check
    $request = new Request(['controller' => $controllerName]);
    
    // Execute the check logic
    $checker->check($request);
    
    // The result of the suffix stripping is stored in the request attributes
    $observed = $request->attributes->get(LegacyControllerConstants::CONTROLLER_NAME_ATTRIBUTE);
    echo "Observed controller name: $observed\n";
    
    /**
     * Expected behavior:
     * Input: 'AdminTestController'
     * Result: 'AdminTest'
     * 
     * Buggy behavior:
     * Input: 'AdminTestController'
     * Result: 'AdminTestController' (because str_ends_with was swapped, the if block was skipped)
     * or 'Controller' (if the if block was entered but substr was buggy)
     */
    if ($observed === 'AdminTest') {
        exit(0);
    } else {
        echo "Bug detected: expected 'AdminTest', but got '$observed'\n";
        exit(1);
    }
} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
