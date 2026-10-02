<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #32112, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Ticket: Unexpected error in 'Design > link widget' page
 * The bug is that Tools::clearSf2Cache() does nothing if the Symfony container is not available.
 * The fix introduces a fallback method removeSymfonyCache() that manually deletes the cache directory.
 */

// In some CLI environments, SymfonyContainer might not be loaded.
// Since Tools::clearSf2Cache() calls SymfonyContainer::getInstance(), 
// we must ensure the class exists to avoid a fatal error, 
// and make it return null to trigger the fallback logic.
if (!class_exists('SymfonyContainer')) {
    class SymfonyContainer {
        public static function getInstance() {
            return null;
        }
    }
}

echo "Testing Tools::clearSf2Cache fallback mechanism...\n";

try {
    // 1. Call the method. 
    // If the fix is applied, it will call the private method removeSymfonyCache().
    // If the fix is not applied, it will simply return null.
    Tools::clearSf2Cache();
    echo "Tools::clearSf2Cache() executed.\n";

    // 2. Verify the fix is present using Reflection.
    // The core of the fix is the addition of the private static method 'removeSymfonyCache'.
    $ref = new ReflectionClass('Tools');
    if ($ref->hasMethod('removeSymfonyCache')) {
        $method = $ref->getMethod('removeSymfonyCache');
        if ($method->isStatic() && $method->isPrivate()) {
            echo "Success: Method removeSymfonyCache exists and is private static.\n";
            exit(0);
        } else {
            echo "Failure: Method removeSymfonyCache exists but has wrong visibility/staticity.\n";
            exit(1);
        }
    } else {
        echo "Failure: Method removeSymfonyCache not found. The fix is not applied.\n";
        exit(1);
    }
} catch (\Throwable $t) {
    echo "An unexpected error occurred: " . $t->getMessage() . "\n";
    exit(1);
}
