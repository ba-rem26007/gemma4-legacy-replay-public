<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29104, validé pre/post automatiquement
require 'config/config.inc.php';

try {
    // 1. Setup: Enable "Re-display cart at login"
    Configuration::updateValue('PS_CART_FOLLOWING', 1);
    
    $context = Context::getContext();
    $context->language = new Language(1);
    $context->currency = new Currency(1);

    // 2. Data: Ensure we have a customer and a guest
    $customer = new Customer(1);
    if (!Validate::isLoadedObject($customer)) {
        $customer = new Customer();
        $customer->firstname = 'Test';
        $customer->lastname = 'User';
        $customer->email = 'test@example.com';
        $customer->passwd = 'password123';
        $customer->add();
    }

    // Create a real Guest object to avoid any DB integrity issues
    $guest = new Guest();
    $guest->id_customer = 0;
    $guest->add();
    $targetGuestId = (int)$guest->id;

    // 3. Create an abandoned cart linked to this guest and customer
    $cart = new Cart();
    $cart->id_currency = 1;
    $cart->id_lang = 1;
    $cart->id_customer = (int)$customer->id;
    $cart->id_guest = $targetGuestId;
    $cart->add();
    $cart->updateQty(1, 1); // Add product 1 to make it recoverable

    echo "Target Guest ID: $targetGuestId\n";
    echo "Target Cart ID: " . $cart->id . "\n";

    // Verify that this cart is indeed the one that will be recovered
    $recoveredId = (int)Cart::lastNoneOrderedCart((int)$customer->id);
    if ($recoveredId !== (int)$cart->id) {
        echo "SETUP ERROR: Cart was not recognized as the last none ordered cart.\n";
        exit(1);
    }

    // 4. Simulate Login: 
    // We set the cookie id_guest to a different value to prove it is (or isn't) overwritten
    $initialGuestId = 555;
    $context->cookie->id_guest = $initialGuestId;
    $context->cookie->id_cart = 0; // Ensure cart recovery is triggered
    $context->cart = null;
    $context->customer = null;

    echo "Cookie Guest ID before login: " . $context->cookie->id_guest . "\n";

    // Trigger the recovery logic
    $context->updateCustomer($customer);

    // 5. Assertions
    $observedGuestId = (int)$context->cookie->id_guest;
    $recoveredCart = $context->cart;

    if (!Validate::isLoadedObject($recoveredCart)) {
        echo "FAILURE: No cart was recovered by updateCustomer().\n";
        exit(1);
    }

    echo "Recovered Cart ID: " . $recoveredCart->id . "\n";
    echo "Observed Cookie Guest ID after login: $observedGuestId\n";

    // The bug: Before the fix, $context->cookie->id_guest remains $initialGuestId (555).
    // After the fix, it must be updated to the cart's id_guest ($targetGuestId).
    if ($observedGuestId === $targetGuestId) {
        echo "SUCCESS: Guest ID in cookie was correctly updated to match the recovered cart.\n";
        exit(0);
    } else {
        echo "FAILURE: Guest ID in cookie ($observedGuestId) was not updated to match Cart Guest ID ($targetGuestId).\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "EXCEPTION: " . $t->getMessage() . "\n";
    exit(1);
}
