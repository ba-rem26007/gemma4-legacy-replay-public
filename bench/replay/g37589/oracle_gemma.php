<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37589, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\Presenter\AbstractLazyArray;

/**
 * The bug is a Warning: Undefined array key "isRewritable" in AbstractLazyArray::offsetSet.
 * This happens when trying to overwrite a key that was added via appendArray() 
 * (which sets type='variable' but does not set 'isRewritable').
 */

$warningTriggered = false;

// Custom error handler to capture the specific Warning
set_error_handler(function ($errno, $errstr) use (&$warningTriggered) {
    if (strpos($errstr, 'isRewritable') !== false) {
        $warningTriggered = true;
    }
    return true;
});

try {
    // AbstractLazyArray is abstract, we create a minimal concrete implementation to test it
    $lazyArray = new class extends AbstractLazyArray {};

    // 1. Add a variable using appendArray.
    // This creates an entry in arrayAccessList: ['type' => 'variable', 'value' => 'initial']
    // Note that 'isRewritable' is NOT set for 'variable' types.
    $lazyArray->appendArray(['test_key' => 'initial_value']);
    
    echo "Initial value: " . $lazyArray['test_key'] . "\n";

    // 2. Trigger offsetSet by assigning a new value to the same key.
    // Before fix: it checks !$offsetData['isRewritable'] BEFORE checking if type is 'variable'.
    // Since 'isRewritable' is missing, PHP emits a Warning.
    $lazyArray['test_key'] = 'new_value';
    
    echo "Updated value: " . $lazyArray['test_key'] . "\n";

} catch (\Throwable $t) {
    echo "Fatal error encountered: " . $t->getMessage() . "\n";
    restore_error_handler();
    exit(1);
}

restore_error_handler();

if ($warningTriggered) {
    echo "Observation: Warning 'Undefined array key isRewritable' was triggered.\n";
    exit(1); // Bug still present
} else {
    echo "Observation: No warning triggered. The check is now safe.\n";
    exit(0); // Bug fixed
}
