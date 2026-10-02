<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37819, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
use PrestaShop\PrestaShop\Core\Form\IdentifiableObject\DataHandler\CarrierFormDataHandler;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Command\EditCarrierCommand;

/**
 * Mock of the CommandBus implementing the required interface.
 * This simulates the behavior of the CommandHandler that throws the exception
 * when a carrier is configured as both free and having handling fees.
 */
class MockCommandBus implements CommandBusInterface
{
    public function handle($command)
    {
        if ($command instanceof EditCarrierCommand) {
            $ref = new ReflectionClass($command);
            
            $isFreeProp = $ref->getProperty('isFree');
            $isFreeProp->setAccessible(true);
            $isFree = $isFreeProp->getValue($command);
            
            $handlingProp = $ref->getProperty('additionalHandlingFee');
            $handlingProp->setAccessible(true);
            $hasHandling = $handlingProp->getValue($command);
            
            if ($isFree === true && $hasHandling === true) {
                throw new \Exception('Carrier cannot be both shipping handling and free');
            }
        }
        
        // Return a dummy object that implements getValue() as expected by CarrierFormDataHandler
        return new class {
            public function getValue() { return 1; }
        };
    }
}

// Ensure we have a carrier to update
$carrier = new Carrier(1);
if (!Validate::isLoadedObject($carrier)) {
    $carrier = new Carrier();
    $carrier->name = 'Test Carrier';
    $carrier->active = 1;
    $carrier->add();
}

// Data that triggers the bug: is_free = true AND has_additional_handling_fee = true
// Note: localized_delay must be an array and shipping_method must be 1 or 2
$data = [
    'general_settings' => [
        'name' => 'My carrier',
        'localized_delay' => [1 => '2-3 days'],
        'grade' => 1,
        'active' => 1,
        'tracking_url' => '',
        'group_access' => [],
        'associated_shops' => [1],
        'logo' => null,
    ],
    'shipping_settings' => [
        'has_additional_handling_fee' => true,
        'is_free' => true,
        'shipping_method' => 1,
        'range_behavior' => 0,
        'zones' => [],
    ],
    'size_weight_settings' => [
        'max_width' => 0,
        'max_height' => 0,
        'max_depth' => 0,
        'max_weight' => 0,
    ],
];

try {
    // Instantiate the handler with our mock bus implementing CommandBusInterface
    $handler = new CarrierFormDataHandler(new MockCommandBus());
    
    echo "Attempting to update carrier with is_free=true and has_additional_handling_fee=true...\n";
    $handler->update($carrier->id, $data);
    
    echo "Success: No exception thrown. The fix is working.\n";
    exit(0);
} catch (\Throwable $e) {
    echo "Caught exception: " . $e->getMessage() . "\n";
    if ($e->getMessage() === 'Carrier cannot be both shipping handling and free') {
        echo "Bug reproduced: Exception thrown as expected before fix.\n";
        exit(1);
    }
    echo "An unexpected exception occurred: " . $e->getMessage() . "\n";
    exit(1);
}
