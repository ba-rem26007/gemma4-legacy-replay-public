<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #31514, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Test for Ticket: Sorting of attributes do not save Prestashop 8.0.1
 * 
 * The bug is that properties like 'position_group_identifier' were not declared 
 * in HelperCore, making them dynamic properties. The fix declares them explicitly 
 * and changes the check from isset() to === null.
 */

// Setup context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->employee = new Employee(1);

try {
    // Use Reflection to instantiate AdminController without calling the constructor
    // to avoid potential crashes in CLI environment (e.g. missing tokens/controllers).
    $adminCtrlRef = new ReflectionClass('AdminController');
    $controller = $adminCtrlRef->newInstanceWithoutConstructor();
    
    // Set the protected property 'position_group_identifier'
    $prop = $adminCtrlRef->getProperty('position_group_identifier');
    $prop->setAccessible(true);
    $testValue = 'attr_group_test_123';
    $prop->setValue($controller, $testValue);
    
    // Instantiate HelperList (which extends Helper/HelperCore)
    $helper = new HelperList();
    
    // Call the method touched by the fix
    $controller->setHelperDisplay($helper);
    
    // 1. Verify the value was transferred
    $observedValue = $helper->position_group_identifier;
    
    // 2. Verify the property is actually declared in the class (not just dynamic)
    $helperRef = new ReflectionClass('Helper');
    $isDeclared = $helperRef->hasProperty('position_group_identifier');
    
    echo "Expected value: $testValue\n";
    echo "Observed value: " . var_export($observedValue, true) . "\n";
    echo "Property declared in Helper: " . ($isDeclared ? 'Yes' : 'No') . "\n";
    
    if ($observedValue === $testValue && $isDeclared) {
        exit(0);
    } else {
        echo "Error: The property was either not transferred or not declared in HelperCore.\n";
        exit(1);
    }
} catch (\Throwable $t) {
    echo "Fatal error during test: " . $t->getMessage() . "\n";
    exit(1);
}
