<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33954, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Test for Ticket: Undefined context currency in actionCartSave
 * 
 * The bug is that FrontController::init() calls Tools::setCurrency() but does not
 * assign the resulting Currency object to the Context. 
 * 
 * To detect this, we simulate a scenario where the Context has one currency (e.g., default),
 * but the Cookie requests another. Without the fix, the Context currency remains 
 * unchanged despite the call to Tools::setCurrency().
 */

try {
    $context = Context::getContext();
    
    // 1. Setup Currencies
    $id_currency_1 = (int)Currency::getDefaultCurrencyId();
    
    // Create a second currency to ensure we have a different one to switch to
    $c2 = new Currency();
    $c2->iso_code = 'USD';
    $c2->numeric_iso_code = '840';
    $c2->conversion_rate = 1.1;
    $c2->active = 1;
    if (!$c2->add()) {
        throw new Exception("Failed to create second currency for test");
    }
    $id_currency_2 = (int)$c2->id;

    // 2. Simulate the state BEFORE FrontController::init()
    // The context is initialized with the default currency
    $context->currency = new Currency($id_currency_1);
    
    // The user has selected a different currency in the cookie
    $context->cookie->id_currency = $id_currency_2;

    // Clear request to avoid redirects in init()
    $_GET = [];
    $_POST = [];
    $_REQUEST = [];

    echo "Default Currency ID: $id_currency_1\n";
    echo "Requested Cookie Currency ID: $id_currency_2\n";
    echo "Context Currency before init(): " . (int)$context->currency->id . "\n";

    // 3. Execute the code under test
    $fc = new FrontController();
    $fc->init();

    // 4. Validation
    // After init(), the context currency MUST be updated to match the cookie's currency.
    // If the fix is missing, $context->currency remains the object created at step 2.
    $observedCurrencyId = (int)$context->currency->id;
    echo "Context Currency after init(): $observedCurrencyId\n";

    if ($observedCurrencyId === $id_currency_2) {
        echo "SUCCESS: Context currency was updated to match the cookie.\n";
        exit(0);
    } else {
        echo "FAILURE: Context currency remained $observedCurrencyId, expected $id_currency_2.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "FATAL ERROR: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
    exit(1);
}
