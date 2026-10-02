<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37925, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Core\Cart\Calculator;
use PrestaShop\PrestaShop\Core\Cart\FeesCalculator;

// Setup Context to avoid "If no employee is assigned..." errors
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);
$context->employee = new Employee(1);

try {
    // 1. Setup Data: Customer
    $customer = new Customer(1);
    if (!Validate::isLoadedObject($customer)) {
        $customer = new Customer();
        $customer->firstname = 'Test';
        $customer->lastname = 'User';
        $customer->email = 'test@example.com';
        $customer->passwd = 'password123';
        $customer->add();
    }

    // 2. Setup Data: Product with price 21.525 (triggers rounding bug)
    $product = new Product();
    $product->price = 21.525000;
    $product->id_category_default = 2;
    $product->id_tax_rules_group = 0; // No tax to isolate the rounding issue
    $product->name = array_fill(1, 1, 'Bug Product');
    $product->link_rewrite = array_fill(1, 1, 'bug-product');
    $product->add();

    // 3. Setup Data: Cart
    $cart = new Cart();
    $cart->id_customer = $customer->id;
    $cart->id_currency = 1;
    $cart->id_lang = 1;
    $cart->add();
    $cart->updateQty(1, $product->id);
    $context->cart = $cart;

    // 4. Setup Data: Cart Rule
    // We set a discount that matches the rounded price (21.53)
    // Before fix: 21.525 - 21.53 = -0.005
    // After fix: round(21.525) - 21.53 = 21.53 - 21.53 = 0
    $cartRule = new CartRule();
    $cartRule->name = array_fill(1, 1, 'Free Order Discount');
    $cartRule->reduction_amount = 21.53;
    $cartRule->id_customer = $customer->id;
    $cartRule->id_currency = 1;
    $cartRule->active = 1;
    $cartRule->quantity = 1;
    $cartRule->quantity_per_user = 1;
    $cartRule->date_from = date('Y-m-d H:i:s', strtotime('-1 day'));
    $cartRule->date_to = date('Y-m-d H:i:s', strtotime('+1 day'));
    $cartRule->add();
    $cart->addCartRule($cartRule->id);

    // 5. Instantiate Calculator and dependencies
    // We use the full namespace and ensure the classes are loaded
    if (!class_exists(FeesCalculator::class)) {
        require_once _PS_ROOT_DIR_ . '/src/Core/Cart/AmountImmutable.php';
        require_once _PS_ROOT_DIR_ . '/src/Core/Cart/FeesCalculator.php';
        require_once _PS_ROOT_DIR_ . '/src/Core/Cart/Calculator.php';
    }

    $feesCalculator = new FeesCalculator($cart);
    $calculator = new Calculator($cart, $feesCalculator);

    // Execute calculation
    $calculator->processCalculation();

    // Get the total
    $totalAmount = $calculator->getTotal();
    
    // AmountImmutable provides getValue() to get the float value
    $totalValue = (float)$totalAmount->getValue();

    echo "Product Price: 21.525\n";
    echo "Discount: 21.53\n";
    echo "Calculated Total: $totalValue\n";

    // The bug is that the total is -0.005 instead of 0.000
    if (abs($totalValue) < 0.0001) {
        echo "Success: Total is rounded to 0.\n";
        exit(0);
    } else {
        echo "Bug detected: Total is $totalValue (expected 0).\n";
        exit(1);
    }

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
