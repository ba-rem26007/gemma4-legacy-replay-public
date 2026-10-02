<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29619, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Test for Ticket: Hook actionObjectUpdateAfter - Infinite loop that leads to memory limit in FO
 * 
 * The bug is a recursive loop:
 * 1. Cookie::isSessionAlive() is called (e.g., via Customer::isLogged() in Hook::getAllHookRegistrations)
 * 2. -> Cookie::getSession()
 * 3. -> CustomerSession::save()
 * 4. -> ObjectModel::update()
 * 5. -> Hook::exec('actionObjectUpdateAfter')
 * 6. -> Hook::getHookModuleExecList()
 * 7. -> Hook::getAllHookRegistrations()
 * 8. -> Customer::isLogged()
 * 9. -> Cookie::isSessionAlive() -> LOOP
 */

// 1. Setup Context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);
$cookie = Context::getContext()->cookie;

// 2. Setup Customer
$customer = new Customer(1);
if (!Validate::isLoadedObject($customer)) {
    $customer = new Customer();
    $customer->firstname = 'Test';
    $customer->lastname = 'User';
    $customer->email = 'test@example.com';
    $customer->passwd = 'password';
    $customer->add();
}
// The loop requires the customer object to be in context for Hook::getAllHookRegistrations to call isLogged()
Context::getContext()->customer = $customer;

// 3. Setup CustomerSession
// We need a session in the DB so that getSession() loads it and save() triggers update().
$session = new CustomerSession();
$session->id_customer = (int)$customer->id;
$session->save();
$id_session_pk = (int)$session->id;

// 4. Simulate logged-in state in the cookie
$cookie->id_customer = (int)$customer->id;
$cookie->session_id = $id_session_pk;

// 5. Setup Hook and Module to close the loop
// The loop only occurs if 'actionObjectUpdateAfter' is registered and has a module attached.
$hookName = 'actionObjectUpdateAfter';
$id_hook = (int)Db::getInstance()->getValue('SELECT id_hook FROM '._DB_PREFIX_.'hook WHERE name = "'.pSQL($hookName).'"');
if (!$id_hook) {
    Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'hook (name) VALUES ("'.pSQL($hookName).'")');
    $id_hook = (int)Db::getInstance()->getValue('SELECT id_hook FROM '._DB_PREFIX_.'hook WHERE name = "'.pSQL($hookName).'"');
}

$moduleName = 'loop_test_module';
$id_module = (int)Db::getInstance()->getValue('SELECT id_module FROM '._DB_PREFIX_.'module WHERE name = "'.pSQL($moduleName).'"');
if (!$id_module) {
    Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'module (name, active) VALUES ("'.pSQL($moduleName).'", 1)');
    $id_module = (int)Db::getInstance()->getValue('SELECT id_module FROM '._DB_PREFIX_.'module WHERE name = "'.pSQL($moduleName).'"');
}

Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.'hook_module WHERE id_hook = '.$id_hook);
Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'hook_module (id_module, id_hook) VALUES ('.$id_module.', '.$id_hook.')');

echo "Setup complete. Customer ID: {$customer->id}, Session PK: {$id_session_pk}\n";

// 6. Trigger the loop
// We set a memory limit that is low enough to crash during recursion but high enough for PS to boot.
ini_set('memory_limit', '64M');

try {
    echo "Triggering Cookie::isSessionAlive() to detect infinite loop...\n";
    
    /**
     * Calling isSessionAlive() starts the chain.
     * Without the fix:
     * isSessionAlive -> getSession -> save -> update -> Hook::exec('actionObjectUpdateAfter') 
     * -> getAllHookRegistrations -> isLogged -> isSessionAlive -> LOOP.
     * 
     * With the fix:
     * The first call to getSession() caches the session object in Cookie::$session.
     * The second call (inside the loop) returns the cached object without calling save().
     */
    $result = $cookie->isSessionAlive();
    
    echo "Success: No infinite loop detected. Result: " . ($result ? 'Alive' : 'Dead') . "\n";
    exit(0);
} catch (\Throwable $t) {
    echo "Caught expected crash or exception: " . $t->getMessage() . "\n";
    exit(1);
}
