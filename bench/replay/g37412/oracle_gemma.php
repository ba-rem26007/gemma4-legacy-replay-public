<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37412, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Wrapper to expose the protected method createOrderCartRules from PaymentModule
 */
class TestPaymentModule extends PaymentModule
{
    public function publicCreateOrderCartRules($order, $cart, $order_list, &$total_reduction_value_ti, &$total_reduction_value_tex, $id_order_state)
    {
        return $this->createOrderCartRules($order, $cart, $order_list, $total_reduction_value_ti, $total_reduction_value_tex, $id_order_state);
    }
}

try {
    // Setup Context
    $context = Context::getContext();
    $context->shop = new Shop(1);
    $context->language = new Language(1);
    $context->currency = new Currency(1);

    // Ensure Customer exists and has a secure_key to avoid Integrity constraint violation
    $customer = new Customer(1);
    if (!Validate::isLoadedObject($customer)) {
        $customer = new Customer();
        $customer->firstname = 'Test';
        $customer->lastname = 'Test';
        $customer->email = 'test@test.com';
        $customer->passwd = 'password123';
        $customer->secure_key = Tools::passwdGen(32);
        $customer->add();
    }
    $context->customer = $customer;

    // Ensure Address exists
    $address = new Address(1);
    if (!Validate::isLoadedObject($address)) {
        $address = new Address();
        $address->firstname = 'Test';
        $address->lastname = 'Test';
        $address->address1 = '123 Test St';
        $address->postcode = '75001';
        $address->city = 'Paris';
        $address->id_country = 1;
        $address->add();
    }

    // 1. Create a CartRule configured as a Gift Product
    // A gift product rule has a reduction_amount of 0.
    // Before the fix, this rule is skipped because $values['tax_excl'] is 0.
    $cartRule = new CartRule();
    $cartRule->name = [1 => 'Gift Product Rule'];
    $cartRule->gift_product = 1; 
    $cartRule->reduction_amount = 0;
    $cartRule->reduction_tax = 1;
    $cartRule->quantity = 100;
    $cartRule->quantity_per_user = 100;
    $cartRule->date_from = '2000-01-01 00:00:00';
    $cartRule->date_to = '2100-01-01 00:00:00';
    $cartRule->active = 1;
    $cartRule->add();

    // 2. Create a Cart and link the rule
    $cart = new Cart();
    $cart->id_currency = 1;
    $cart->id_lang = 1;
    $cart->id_customer = $customer->id;
    $cart->add();
    $cart->addCartRule($cartRule->id);
    $context->cart = $cart;

    // 3. Create an Order associated with this cart
    $order = new Order();
    $order->id_address_invoice = $address->id;
    $order->id_address_delivery = $address->id;
    $order->id_cart = $cart->id;
    $order->id_currency = 1;
    $order->id_lang = 1;
    $order->id_customer = $customer->id;
    $order->id_carrier = 1;
    $order->payment = 'TestPayment';
    $order->module = 'ps_checkpayment';
    $order->total_paid = 10.00;
    $order->total_paid_real = 10.00;
    $order->total_products = 10.00;
    $order->total_products_wt = 10.00;
    $order->conversion_rate = 1;
    $order->add();

    // Add a product to the order so getProducts() is not empty
    $od = new OrderDetail();
    $od->id_order = $order->id;
    $od->product_id = 1;
    $od->product_quantity = 1;
    $od->product_price = 10.00;
    $od->add();

    $order_list = $order->getProducts();

    // 4. Call the target method
    $pm = new TestPaymentModule();
    $pm->context = $context;

    $total_ti = 0;
    $total_tex = 0;
    $id_order_state = 1;

    $applied_rules = $pm->publicCreateOrderCartRules(
        $order,
        $cart,
        $order_list,
        $total_ti,
        $total_tex,
        $id_order_state
    );

    // 5. Verify if the gift product rule was kept
    $found = false;
    if (is_array($applied_rules)) {
        foreach ($applied_rules as $rule) {
            if (isset($rule['id_cart_rule']) && (int)$rule['id_cart_rule'] === (int)$cartRule->id) {
                $found = true;
                break;
            }
        }
    }

    echo "Cart Rule ID: " . $cartRule->id . "\n";
    echo "Number of rules applied to order: " . count($applied_rules) . "\n";
    echo "Gift rule found: " . ($found ? 'YES' : 'NO') . "\n";

    exit($found ? 0 : 1);

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
