<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #30993, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * The bug is a PHP Notice: "Trying to access array offset on value of type null"
 * in WebserviceOutputBuilder::renderAssociations when rendering a blank schema.
 * We use a custom error handler to convert this notice into an exception.
 */
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

try {
    // 1. Setup: We need a Customer object (empty, as for schema=blank)
    $customer = new Customer();
    
    // 2. Setup: WebserviceOutputBuilder requires a formatter.
    // We create a dummy formatter using variadic arguments to avoid ArgumentCountError
    // regardless of the PrestaShop version's internal calls.
    $builderReflection = new ReflectionClass('WebserviceOutputBuilder');
    $builder = $builderReflection->newInstanceWithoutConstructor();
    
    $dummyFormatter = new class {
        public function renderNodeHeader(...$args) { 
            return "header"; 
        }
        public function renderNodeFooter(...$args) { 
            return "footer"; 
        }
        public function renderField(...$args) { 
            return "field"; 
        }
    };
    
    $prop = new ReflectionProperty('WebserviceOutputBuilder', 'objectRender');
    $prop->setAccessible(true);
    $prop->setValue($builder, $dummyFormatter);
    
    // 3. Get the webservice parameters for the Customer entity
    $ws_params = $customer->getWebserviceParameters();
    
    echo "Testing renderSchema for Customer (blank schema)...\n";
    
    /**
     * renderSchema is a protected method. 
     * We use Reflection to call it directly to trigger the bug in renderAssociations.
     */
    $method = new ReflectionMethod('WebserviceOutputBuilder', 'renderSchema');
    $method->setAccessible(true);
    
    // This call triggers renderAssociations -> renderFlatAssociation
    // For a new Customer (id=0), the association 'groups' will have a null value,
    // triggering the notice: $value['id'] where $value is null.
    $method->invoke($builder, $customer, $ws_params);
    
    echo "Success: No PHP Notice triggered. The bug is fixed.\n";
    exit(0);
} catch (\Throwable $t) {
    echo "Bug detected: " . $t->getMessage() . "\n";
    // If we caught an ErrorException (from our error handler), the bug is still present.
    exit(1);
}
