<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33212, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

try {
    // 1. Create a generic CartRule (id_customer = 0)
    // This rule should NOT be deleted when a non-existent customer is "deleted"
    $crGeneric = new CartRule();
    $crGeneric->name = [1 => 'Generic Rule Test'];
    $crGeneric->code = 'GENERIC_TEST_CODE';
    $crGeneric->date_from = '2020-01-01 00:00:00';
    $crGeneric->date_to = '2030-01-01 00:00:00';
    $crGeneric->id_customer = 0;
    $crGeneric->add();
    
    echo "Generic CartRule created with code: GENERIC_TEST_CODE\n";

    // 2. Create a specific CartRule for a real customer
    // Use demo data: Customer 1 is guaranteed to exist
    $customer = new Customer(1);
    if (!Validate::isLoadedObject($customer)) {
        // Fallback if demo data is missing: create a customer with a valid password
        $customer = new Customer();
        $customer->firstname = 'Test';
        $customer->lastname = 'User';
        $customer->email = 'test@example.com';
        $customer->passwd = 'Password123456!'; // Strong password to pass Validate::isPassword
        $customer->add();
    }

    $crSpecific = new CartRule();
    $crSpecific->name = [1 => 'Specific Rule Test'];
    $crSpecific->code = 'SPECIFIC_TEST_CODE';
    $crSpecific->date_from = '2020-01-01 00:00:00';
    $crSpecific->date_to = '2030-01-01 00:00:00';
    $crSpecific->id_customer = (int)$customer->id;
    $crSpecific->add();
    
    echo "Specific CartRule created for customer ID: " . $customer->id . "\n";

    // 3. Trigger the bug: Instantiate a Customer that is not loaded (ID -1) and call delete()
    // In the buggy version, $badCustomer->id is null, cast to (int) becomes 0.
    // CartRule::deleteByIdCustomer(0) then deletes all generic rules.
    $badCustomer = new Customer(-1);
    echo "Attempting to delete non-loaded customer (ID -1)...\n";
    $badCustomer->delete();

    // 4. Verify if the generic rule still exists
    $exists = CartRule::cartRuleExists('GENERIC_TEST_CODE');
    
    echo "Generic rule still exists: " . ($exists ? 'YES' : 'NO') . "\n";

    if (!$exists) {
        echo "FAILURE: Generic CartRule was deleted because id_customer=0 was passed to deleteByIdCustomer.\n";
        exit(1);
    }

    echo "SUCCESS: Generic CartRule was preserved.\n";
    exit(0);

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
