<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #32201, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Core\Form\IdentifiableObject\DataHandler\ProductImageFormDataHandler;
use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;

// Setup Context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

/**
 * Mock CommandBus to avoid executing actual domain logic
 */
$mockBus = new class implements CommandBusInterface {
    public function handle($command)
    {
        return null;
    }
};

$handler = new ProductImageFormDataHandler($mockBus);

// Convert PHP Warnings into ErrorExceptions to detect the "Undefined array key" bug
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

try {
    /**
     * The bug occurs in ProductImageFormDataHandler::update()
     * when the 'shop_id' key is missing from the $data array.
     * 
     * Code before fix: if ($data['shop_id']) { ... } -> Triggers Warning
     * Code after fix:  if (!empty($data['shop_id'])) { ... } -> Safe
     */
    $data = []; // Missing 'shop_id'

    $handler->update(1, $data);

    // If we reach here, no warning was triggered
    restore_error_handler();
    echo "Success: No 'Undefined array key' warning triggered.\n";
    exit(0);
} catch (\Throwable $t) {
    restore_error_handler();
    echo "Bug detected: " . $t->getMessage() . "\n";
    
    // If the error is specifically about the missing shop_id key, the bug is present
    if (strpos($t->getMessage(), 'Undefined array key "shop_id"') !== false) {
        exit(1);
    }
    
    // Other unexpected errors are also failures
    exit(1);
}
