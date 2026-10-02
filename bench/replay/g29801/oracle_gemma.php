<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29801, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Core\Crypto\Hashing;

/**
 * Minimal implementation of TranslatorInterface to satisfy CustomerPersisterCore
 * without relying on the Symfony container.
 */
if (!class_exists('MockTranslator')) {
    class MockTranslator implements \Symfony\Component\Translation\TranslatorInterface {
        public function trans($id, array $parameters = [], $domain = null, $locale = null) {
            return $id;
        }
        public function transChoice($id, array $parameters = [], $defaultLocale = null) {
            return $id;
        }
        public function getLocale() { return 'fr'; }
        public function setLocale($locale) {}
        public function setFallbackLocale($locale) {}
    }
}

try {
    $context = Context::getContext();
    $context->shop = new Shop(1);
    $context->language = new Language(1);
    $context->currency = new Currency(1);

    // 1. Create a Guest Customer
    $customer = new Customer();
    $customer->firstname = 'Guest';
    $customer->lastname = 'User';
    $customer->email = 'guest_test_' . uniqid() . '@example.com';
    $customer->passwd = Tools::encrypt('guestpass');
    $customer->is_guest = true;
    $customer->add();

    $guestGroupId = (int)Configuration::get('PS_GUEST_GROUP');
    $customer->addGroups([$guestGroupId]);

    // The CustomerPersister checks if the customer being updated is the one in the session
    // to prevent unauthorized updates of other customers.
    $context->customer = $customer;

    echo "Initial state - Customer ID: " . $customer->id . "\n";
    echo "is_guest: " . ($customer->is_guest ? '1' : '0') . "\n";
    echo "Groups: " . implode(',', $customer->getGroups()) . "\n";

    // 2. Setup CustomerPersister
    $crypto = new Hashing();
    $translator = new MockTranslator();
    
    // Instantiate the Core class directly
    $persister = new CustomerPersisterCore($context, $crypto, $translator, true);

    // 3. Convert Guest to Customer
    // Providing a password triggers the conversion logic in CustomerPersister::update
    $plainTextPassword = 'newpassword123';
    
    echo "Attempting conversion...\n";
    $result = $persister->save($customer, $plainTextPassword);

    if (!$result) {
        echo "Persister save returned false. Errors: " . implode(', ', $persister->getErrors()) . "\n";
    }

    // Refresh the customer object from DB to verify changes
    $customer = new Customer($customer->id);
    $groups = $customer->getGroups();
    $customerGroupId = (int)Configuration::get('PS_CUSTOMER_GROUP');

    echo "After conversion - is_guest: " . ($customer->is_guest ? '1' : '0') . "\n";
    echo "Groups: " . implode(',', $groups) . "\n";

    // Verification:
    // The customer should no longer be a guest and MUST be assigned to the Customer group.
    // Before the fix, the customer remained in the Guest group.
    if (!$customer->is_guest && in_array($customerGroupId, $groups)) {
        echo "SUCCESS: Customer converted and group assigned correctly.\n";
        exit(0);
    } else {
        echo "FAILURE: Customer group not assigned properly or is_guest still true.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "ERROR: " . $t->getMessage() . "\n";
    echo "File: " . $t->getFile() . " Line: " . $t->getLine() . "\n";
    exit(1);
}
