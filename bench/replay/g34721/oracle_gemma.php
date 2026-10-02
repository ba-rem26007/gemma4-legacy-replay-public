<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #34721, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * The bug is a Fatal Error: Undefined constant "FRONT_LEGACY_CONTEXT"
 * occurring in FrontController::buildContainer().
 * This happens when the constant is not defined, which is the case in CLI
 * or certain admin entry points.
 */

// Setup basic context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);

try {
    // We instantiate FrontController.
    // We use Reflection to call the protected method buildContainer() directly
    // to isolate the bug and avoid side-effects of the full init() process in CLI
    // (like session management or header redirects).
    $fc = new FrontController();
    
    $reflection = new ReflectionClass('FrontController');
    $method = $reflection->getMethod('buildContainer');
    $method->setAccessible(true);

    echo "Testing FrontController::buildContainer()...\n";
    
    // This call will trigger the "Undefined constant" error if the fix is not applied
    $result = $method->invoke($fc);
    
    echo "Success: buildContainer() executed without crashing.\n";
    
    if ($result) {
        echo "Container returned: " . get_class($result) . "\n";
    }

} catch (\Throwable $t) {
    // If the bug is present, it throws an Error (Undefined constant)
    echo "Caught expected error: " . $t->getMessage() . "\n";
    exit(1);
}

exit(0);
