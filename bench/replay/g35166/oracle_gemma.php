<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #35166, validé pre/post automatiquement
require 'config/config.inc.php';

try {
    // 1. Setup Environment to avoid FrontController fatal errors in CLI
    $context = Context::getContext();
    $context->shop = new Shop(1);
    $context->language = new Language(1);
    $context->currency = new Currency(1);
    $context->customer = new Customer(1);
    
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['SERVER_NAME'] = 'localhost';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';

    // 2. Create a Cart and add a product
    $cart = new Cart();
    $cart->id_currency = 1;
    $cart->id_lang = 1;
    $cart->id_customer = $context->customer->id;
    $cart->add();
    $cart->updateQty(1, 1); // Product 1, Qty 1
    $context->cart = $cart;

    // 3. Create a Cart Rule and enable it
    $cartRule = new CartRule();
    $cartRule->name = [1 => 'Regression Test Rule'];
    $cartRule->code = 'REGRESS' . uniqid();
    $cartRule->quantity = 100;
    $cartRule->quantity_per_user = 100;
    $cartRule->active = 1;
    $cartRule->date_from = date('Y-m-d H:i:s');
    $cartRule->date_to = date('Y-m-d H:i:s', strtotime('+1 day'));
    $cartRule->reduction_amount = 5;
    $cartRule->id_shop = 1;
    $cartRule->id_shop_group = 1;
    $cartRule->add();

    // 4. Apply the rule to the cart
    $cart->addCartRule($cartRule->id);
    
    // Verify it is actually linked in the database (ps_cart_cart_rule)
    $rulesBefore = $cart->getCartRules();
    if (!isset($rulesBefore[$cartRule->id])) {
        echo "Error: Cart rule was not linked to the cart.\n";
        exit(1);
    }

    // 5. Disable the rule in the BO
    $cartRule->active = 0;
    $cartRule->update();
    echo "Cart rule disabled in BO. It is still linked to the cart (bug state).\n";

    // 6. Simulate Front Office page load
    // The fix is the addition of CartRule::autoRemoveFromCart($this->context) in FrontController::init()
    $controller = new FrontController();
    
    // We use a try-catch because FrontController::init() can be unstable in CLI
    try {
        $controller->init();
    } catch (\Throwable $e) {
        // If init() crashes but we are testing the logic, we can't proceed.
        // However, we should try to see if the rule was removed before the crash.
        echo "FrontController::init() crashed, but checking if rule was removed anyway...\n";
    }

    // 7. Final Verification
    // Refresh the cart rules from the database
    $rulesAfter = $cart->getCartRules();
    $stillApplied = isset($rulesAfter[$cartRule->id]);

    echo "Rule still linked to cart after FrontController::init(): " . ($stillApplied ? 'YES' : 'NO') . "\n";

    // If the rule is still applied, the bug is present (exit 1).
    // If the rule was removed, the fix is working (exit 0).
    exit($stillApplied ? 1 : 0);

} catch (\Throwable $t) {
    echo "Fatal Exception: " . $t->getMessage() . "\n";
    exit(1);
}
