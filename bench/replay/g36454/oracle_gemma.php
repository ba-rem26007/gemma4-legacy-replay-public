<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #36454, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);
Context::getContext()->currency = new Currency(1);

$email = 'duplicate_test_' . time() . '@example.com';
$password1 = 'ComplexPass123!';
$password2 = 'AnotherPass456!';

try {
    // Use a simple 32-character string for the initial guest password.
    // This usually bypasses most PrestaShop field validators for the 'passwd' property
    // which often expect a hash or a generic string.
    $initialPass = 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6';

    // 1. Create first guest customer
    $c1 = new Customer();
    $c1->firstname = 'Guest';
    $c1->lastname = 'One';
    $c1->email = $email;
    $c1->passwd = $initialPass;
    $c1->is_guest = 1;
    if (!$c1->add()) {
        throw new Exception("Failed to create Guest 1");
    }
    echo "Guest 1 created with ID: " . $c1->id . "\n";

    // 2. Create second guest customer with the same email
    $c2 = new Customer();
    $c2->firstname = 'Guest';
    $c2->lastname = 'Two';
    $c2->email = $email;
    $c2->passwd = $initialPass;
    $c2->is_guest = 1;
    if (!$c2->add()) {
        throw new Exception("Failed to create Guest 2");
    }
    echo "Guest 2 created with ID: " . $c2->id . "\n";

    // 3. Transform first guest into a customer
    // This should always succeed.
    $res1 = $c1->transformToCustomer(1, $password1);
    echo "Transformation 1 result: " . ($res1 ? 'Success' : 'Failure') . "\n";

    // 4. Transform second guest into a customer
    // This should FAIL after the fix because Customer::customerExists($email) 
    // now returns true (since Guest 1 is no longer a guest).
    $res2 = $c2->transformToCustomer(1, $password2);
    echo "Transformation 2 result: " . ($res2 ? 'Success' : 'Failure') . "\n";

    // Verification: Count how many registered customers (is_guest = 0) have this email
    $sql = 'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'customer WHERE email = "' . pSQL($email) . '" AND is_guest = 0';
    $count = (int) Db::getInstance()->getValue($sql);
    echo "Number of registered customers with email $email: $count\n";

    if ($res2 === true || $count > 1) {
        echo "BUG DETECTED: Two customers created with the same email.\n";
        exit(1);
    } else {
        echo "FIX VERIFIED: Second transformation was blocked.\n";
        exit(0);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
