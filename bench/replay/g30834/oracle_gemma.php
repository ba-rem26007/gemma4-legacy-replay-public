<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #30834, validé pre/post automatiquement
require 'config/config.inc.php';

try {
    // Setup Context
    $context = Context::getContext();
    $context->shop = new Shop(1);
    $context->language = new Language(1);
    $context->currency = new Currency(1);

    // Use existing demo customer or create one
    $customer = new Customer(1);
    if (!Validate::isLoadedObject($customer)) {
        $customer = new Customer();
        $customer->firstname = 'John';
        $customer->lastname = 'Doe';
        $customer->email = 'test@example.com';
        $customer->passwd = 'password123';
        $customer->add();
    }

    // Create two addresses for the customer to verify that the filter by id_address works.
    // We use very simple names to avoid ObjectModel validation errors.
    $addr1 = new Address();
    $addr1->id_customer = (int)$customer->id;
    $addr1->alias = 'Home';
    $addr1->firstname = 'John';
    $addr1->lastname = 'Doe';
    $addr1->address1 = '123 Main Street';
    $addr1->city = 'Paris';
    $addr1->id_country = 1;
    $addr1->add();

    $addr2 = new Address();
    $addr2->id_customer = (int)$customer->id;
    $addr2->alias = 'Work';
    $addr2->firstname = 'John';
    $addr2->lastname = 'Doe';
    $addr2->address1 = '456 Office Road';
    $addr2->city = 'Lyon';
    $addr2->id_country = 1;
    $addr2->add();

    // We request specifically the second address
    $targetId = (int)$addr2->id;
    $result = $customer->getSimpleAddress($targetId);

    echo "Target Address ID: $targetId\n";

    /**
     * Analysis of getSimpleAddress() return value:
     * - If the SQL is correct (fixed), it returns exactly one row, so getSimpleAddress returns an associative array.
     * - If the SQL is incorrect (bug), the WHERE clause for id_address is ignored (it becomes part of ORDER BY).
     *   It returns all addresses of the customer, so getSimpleAddress returns an indexed array of arrays.
     */
    if (isset($result[0]) && is_array($result[0])) {
        // The method returned a list of addresses instead of a single one.
        echo "Bug detected: Multiple addresses returned. Count: " . count($result) . "\n";
        exit(1);
    } elseif (isset($result['id']) && (int)$result['id'] === $targetId) {
        // The method returned exactly one address, and it's the correct one.
        echo "Fixed: Single correct address returned.\n";
        exit(0);
    } else {
        echo "Unexpected result format or wrong address returned.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
