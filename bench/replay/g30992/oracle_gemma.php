<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #30992, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\Presenter\Object\ObjectPresenter;

/**
 * Test for Ticket: is_logged property in FO is true even for guests
 * 
 * The bug: FrontController::getTemplateVarCustomer() uses isLogged(true).
 * isLogged(true) returns true if the session is alive (id_guest or id_customer in cookie),
 * even if the user is not actually authenticated (id_customer = 0 in cookie).
 * 
 * To trigger the bug:
 * 1. Session must be alive (id_guest > 0).
 * 2. User must NOT be logged in (id_customer = 0 in cookie).
 * 3. isLogged(true) will return true -> is_logged = true (BUG).
 * 4. isLogged() will return false -> is_logged = false (CORRECT).
 */

// 1. Setup the Global Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

// 2. Simulate a Guest session in the cookie
// We set id_guest to ensure isSessionAlive() returns true
$context->cookie->id_guest = (int)1;
$context->cookie->id_customer = 0;

// 3. Setup a Guest Customer object (not logged in)
// A guest visitor has an id of 0.
$customer = new Customer(); 
$customer->id = 0; 
$customer->id_gender = 1; // Required for the Gender presenter in getTemplateVarCustomer
$context->customer = $customer;

// Verify that the session is indeed considered alive by the system
if (!Context::getContext()->cookie->isSessionAlive()) {
    echo "Setup Error: Session is not alive despite id_guest being set.\n";
    exit(1);
}

// 4. Instantiate FrontController using an anonymous class to bypass protected properties
$fc = new class extends FrontController {
    public function setPresenter($p) {
        $this->objectPresenter = $p;
    }
    public function setContext($c) {
        $this->context = $c;
    }
};

$fc->setContext($context);
$fc->setPresenter(new ObjectPresenter());

try {
    // This method calls $this->context->customer->isLogged(true) in the buggy version
    $vars = $fc->getTemplateVarCustomer();
    $isLogged = $vars['is_logged'];
} catch (\Throwable $e) {
    echo "Error during execution: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Customer ID: " . (int)$customer->id . "\n";
echo "Cookie id_guest: " . (int)$context->cookie->id_guest . "\n";
echo "Cookie id_customer: " . (int)$context->cookie->id_customer . "\n";
echo "Session Alive: " . (Context::getContext()->cookie->isSessionAlive() ? 'true' : 'false') . "\n";
echo "Observed is_logged: " . ($isLogged ? 'true' : 'false') . "\n";

/**
 * ASSERTION:
 * For a guest (id=0) with a live session (id_guest=1):
 * - Before fix: isLogged(true) is called -> returns true -> is_logged = true (FAIL)
 * - After fix: isLogged() is called -> returns (bool)id (0) -> is_logged = false (PASS)
 */
exit($isLogged === false ? 0 : 1);
