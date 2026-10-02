<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #30146, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Test for Ticket: The override directory is deleted when overrides are uninstalled
 * 
 * The bug is that Module::removeOverrideDirectory uses getPathname() for the loop condition.
 * getPathname() returns the path as it was passed to the constructor, without resolving 
 * symlinks or '..' segments. If the path provided to the method contains such segments, 
 * the string comparison fails even if the directory is the same, causing the loop 
 * to continue and delete the root /override directory.
 */

// Module is abstract, we need a concrete implementation to instantiate it
class TestModule extends Module {
    public function __construct() {
        parent::__construct('testmodule');
    }
}

// 1. Setup environment
$dirOverride = realpath(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'override');
if (!$dirOverride) {
    mkdir(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'override', 0777, true);
    $dirOverride = realpath(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'override');
}

$dirClasses = $dirOverride . DIRECTORY_SEPARATOR . 'classes';
if (!is_dir($dirClasses)) {
    mkdir($dirClasses, 0777, true);
}

// Create the files that should be handled
// /override/index.php should be PRESERVED
$indexOverride = $dirOverride . DIRECTORY_SEPARATOR . 'index.php';
file_put_contents($indexOverride, '<?php');

// /override/classes/index.php should be DELETED
$indexClasses = $dirClasses . DIRECTORY_SEPARATOR . 'index.php';
file_put_contents($indexClasses, '<?php');

// Ensure no other files exist in /override/classes to allow the loop to proceed
$files = glob($dirClasses . DIRECTORY_SEPARATOR . '*');
if ($files) {
    foreach ($files as $file) {
        if (basename($file) !== 'index.php') {
            unlink($file);
        }
    }
}

echo "Setup: /override/index.php exists: " . (file_exists($indexOverride) ? 'Yes' : 'No') . "\n";
echo "Setup: /override/classes/index.php exists: " . (file_exists($indexClasses) ? 'Yes' : 'No') . "\n";

try {
    // 2. Call the private method removeOverrideDirectory via Reflection
    $module = new TestModule();
    $reflection = new ReflectionClass($module);
    $method = $reflection->getMethod('removeOverrideDirectory');
    $method->setAccessible(true);

    echo "Executing removeOverrideDirectory with non-normalized path...\n";
    
    /**
     * To trigger the bug, we pass a path that is logically the same as $dirOverride 
     * but string-different. We use a '..' segment.
     * 
     * $directoryOverride = /var/www/html/override
     * $directoryPath = /var/www/html/override/../override/classes
     * 
     * In the loop:
     * 1. $splDir starts at /var/www/html/override/../override/classes
     * 2. $splDir = $splDir->getPathInfo() -> pathname becomes /var/www/html/override/../override
     * 3. Comparison: '/var/www/html/override/../override' !== '/var/www/html/override'
     *    This is TRUE, so the loop continues and deletes /override/index.php.
     * 
     * With the fix:
     * 3. Comparison: $splDir->getRealPath() !== $directoryOverride
     *    '/var/www/html/override' !== '/var/www/html/override'
     *    This is FALSE, so the loop stops.
     */
    $nonNormalizedPath = $dirOverride . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'override' . DIRECTORY_SEPARATOR . 'classes';
    
    $method->invoke($module, $dirOverride, $nonNormalizedPath);

} catch (\Throwable $e) {
    echo "Error during execution: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Verification
$indexExists = file_exists($indexOverride);
$classesExists = is_dir($dirClasses);

echo "Result - /override/index.php exists: " . ($indexExists ? 'Yes' : 'No') . "\n";
echo "Result - /override/classes directory exists: " . ($classesExists ? 'Yes' : 'No') . "\n";

if ($indexExists) {
    echo "SUCCESS: /override/index.php was preserved.\n";
    exit(0);
} else {
    echo "FAILURE: /override/index.php was deleted.\n";
    exit(1);
}
