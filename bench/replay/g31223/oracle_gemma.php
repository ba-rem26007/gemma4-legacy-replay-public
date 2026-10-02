<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #31223, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Core\Module\Exception\ModuleErrorInterface;
use PrestaShop\PrestaShop\Core\Exception\CoreException;

// Use unique class names and check existence to avoid "Cannot declare class" fatal errors
if (!class_exists('TestModuleException_Unique')) {
    class TestModuleException_Unique extends Exception implements ModuleErrorInterface {}
}

if (!class_exists('TestModule_Unique')) {
    class TestModule_Unique extends Module
    {
        public function __construct()
        {
            $this->name = 'testmodule_unique';
            parent::__construct();
        }

        /**
         * This method will be called by Hook::callHookOn
         */
        public function hookActionTest($params)
        {
            throw new TestModuleException_Unique('This is a displayable module error');
        }
    }
}

// We need to test the private method Hook::callHookOn directly because Hook::exec 
// requires the module to be physically present on the disk to instantiate it.
$module = new TestModule_Unique();
$hookName = 'actionTest';
$hookArgs = [];

try {
    $reflection = new ReflectionMethod('Hook', 'callHookOn');
    $reflection->setAccessible(true);
    
    // Invoke the private static method: Hook::callHookOn($module, $hookName, $hookArgs)
    $reflection->invoke(null, $module, $hookName, $hookArgs);
    
    // If we reach here, the exception was swallowed (returned empty string), which is the bug.
    echo "Bug: The ModuleErrorInterface exception was swallowed.\n";
    exit(1);
} catch (TestModuleException_Unique $e) {
    // The fix ensures that exceptions implementing ModuleErrorInterface are re-thrown.
    echo "Success: Caught expected ModuleErrorInterface exception: " . $e->getMessage() . "\n";
    exit(0);
} catch (CoreException $e) {
    // Before the fix, if debug mode was on, the exception was wrapped in a CoreException.
    echo "Bug: The exception was caught and converted to a CoreException.\n";
    exit(1);
} catch (\Throwable $t) {
    // Any other exception is a failure.
    echo "Unexpected exception of type " . get_class($t) . ": " . $t->getMessage() . "\n";
    exit(1);
}
