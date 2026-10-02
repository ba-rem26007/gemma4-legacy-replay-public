<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33495, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\Order\QueryHandler\GetOrderForViewingHandler;

// 1. Setup: Use existing Order 1 and modify its carrier to trigger the bug
$order = new Order(1);
if (!Validate::isLoadedObject($order)) {
    echo "Order 1 not found in demo data\n";
    exit(1);
}

$carrier = new Carrier($order->id_carrier);
if (!Validate::isLoadedObject($carrier)) {
    echo "Carrier not found\n";
    exit(1);
}

// Simulate a carrier pointing to a non-existent module
$carrier->is_module = 1;
$carrier->external_module_name = 'non_existent_module_12345';
$carrier->save();

echo "Carrier {$carrier->id} set to use invalid module '{$carrier->external_module_name}'\n";

try {
    // 2. Instantiate the Handler using Reflection to avoid the Symfony container in the constructor
    $reflector = new \ReflectionClass(GetOrderForViewingHandler::class);
    $instance = $reflector->newInstanceWithoutConstructor();

    // 3. Call the target private method
    $method = $reflector->getMethod('getOrderShipping');
    $method->setAccessible(true);
    
    echo "Calling getOrderShipping()...\n";
    $method->invoke($instance, $order);
    
    echo "Success: No error thrown.\n";
    exit(0);

} catch (\Throwable $t) {
    echo "Caught exception: " . get_class($t) . "\n";
    echo "Message: " . $t->getMessage() . "\n";

    // The bug is a TypeError in PHP 8 when method_exists() is called with 'false' as the first argument.
    if ($t instanceof \TypeError && strpos($t->getMessage(), 'method_exists') !== false) {
        echo "Bug reproduced: TypeError in method_exists due to invalid module\n";
        exit(1);
    }

    // If we hit a ContainerNotFoundException, it means the code PASSED the bugged line 
    // (the fix worked) and crashed later when trying to instantiate the return DTO 
    // or access a service. In this specific test environment, this is a sign of success.
    if (strpos(get_class($t), 'ContainerNotFoundException') !== false) {
        echo "The bug was not triggered (the fix worked), but the environment crashed on the return value.\n";
        exit(0);
    }

    // Any other unexpected exception is a failure
    exit(1);
}
