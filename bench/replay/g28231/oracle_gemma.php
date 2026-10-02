<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #28231, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Test for Ticket: Mail with prices with taxes/no taxes mixed
 * 
 * The bug: When PS_TAX_METHOD is 'EXCL', the order confirmation email 
 * incorrectly uses tax-inclusive values for {total_discounts}, {total_shipping}, 
 * {total_wrapping}, and the voucher reduction in {discounts}.
 */

// 1. Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);
$context->customer = new Customer(1);

// 2. Force Tax Exclusive Mode
Configuration::updateValue('PS_TAX_METHOD', 'EXCL');

// 3. Setup Data
// Product: 100 HT, 20% Tax -> 120 TTC
$product = new Product(1, false, 1);
$product->price = 100.00;
$product->id_tax_rules_group = 1; 
$product->save();

// Address (Required for Order creation)
$address = new Address();
$address->id_country = 1;
$address->firstname = 'Test';
$address->lastname = 'User';
$address->address1 = '123 Street';
$address->postcode = '75000';
$address->city = 'Paris';
$address->alias = 'Home'; // Fix: alias is required
$address->add();

// Cart
$cart = new Cart();
$cart->id_customer = 1;
$cart->id_currency = 1;
$cart->id_lang = 1;
$cart->id_shop = 1;
$cart->id_address_invoice = $address->id;
$cart->id_address_delivery = $address->id;
$cart->add();
$cart->updateQty(1, 1, 1);

// Cart Rule: 10 HT, 20% Tax -> 12 TTC
$cartRule = new CartRule();
$cartRule->name = [1 => 'Test Discount'];
$cartRule->reduction_amount = 10.00;
$cartRule->reduction_tax = 1; // Taxable
$cartRule->id_customer = 1;
$cartRule->quantity = 1;
$cartRule->quantity_per_user = 1;
$cartRule->date_from = date('Y-m-d H:i:s');
$cartRule->date_to = date('Y-m-d H:i:s', strtotime('+1 year'));
$cartRule->active = 1;
$cartRule->add();
$cart->addCartRule($cartRule->id);

// 4. Mock PaymentModule to capture email variables
class TestPaymentModule extends PaymentModule {
    public $captured_vars = [];
    public $active = true;

    public function __construct($name = 'testmod') {
        parent::__construct($name);
    }

    protected function getEmailTemplateContent($template_name, $mail_type, $var) {
        $this->captured_vars = $var;
        return 'dummy content';
    }
}

try {
    $module = new TestPaymentModule();
    $module->context = $context;

    // Ensure carrier is set
    $cart->id_carrier = 1;

    // Execute validateOrder
    // Total paid: 120 (prod) - 12 (discount) = 108
    $module->validateOrder(
        (int)$cart->id,
        2, // Payment accepted
        108.00,
        'TestPayment',
        null,
        [],
        null,
        false,
        false,
        $context->shop
    );

    $vars = $module->captured_vars;

    $price_10 = Tools::getContextLocale($context)->formatPrice(10, $context->currency->iso_code);
    $price_12 = Tools::getContextLocale($context)->formatPrice(12, $context->currency->iso_code);

    echo "PS_TAX_METHOD: " . Configuration::get('PS_TAX_METHOD') . "\n";
    echo "{total_discounts}: " . ($vars['{total_discounts}'] ?? 'N/A') . "\n";
    echo "{discounts}: " . ($vars['{discounts}'] ?? 'N/A') . "\n";

    $success = true;

    // Assertion 1: {total_discounts} must be HT (10) not TTC (12)
    if (($vars['{total_discounts}'] ?? '') === $price_12) {
        echo "FAIL: {total_discounts} is tax-incl ($price_12), expected tax-excl ($price_10)\n";
        $success = false;
    }

    // Assertion 2: The voucher reduction in {discounts} HTML must be HT (10) not TTC (12)
    if (strpos($vars['{discounts}'] ?? '', $price_12) !== false && strpos($vars['{discounts}'] ?? '', $price_10) === false) {
        echo "FAIL: {discounts} contains tax-incl price ($price_12), expected tax-excl ($price_10)\n";
        $success = false;
    }

    exit($success ? 0 : 1);

} catch (\Throwable $t) {
    echo "Fatal Error: " . $t->getMessage() . "\n";
    exit(1);
}
