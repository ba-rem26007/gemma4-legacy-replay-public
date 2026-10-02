<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33608, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);
Context::getContext()->currency = new Currency(1);
$customer = new Customer(1);
if (!Validate::isLoadedObject($customer)) {
    $customer = new Customer();
    $customer->firstname = 'Test';
    $customer->lastname = 'User';
    $customer->email = 'test@example.com';
    $customer->passwd = 'password';
    $customer->add();
}
Context::getContext()->customer = $customer;

// 1. Configure 2 products with different VAT rates
// Product 1: 21% tax (Tax Rules Group 1)
$p1 = new Product(1);
$p1->price = 30.00;
$p1->id_tax_rules_group = 1; 
$p1->save();

// Product 2: 10% tax (Tax Rules Group 2)
$p2 = new Product(2);
$p2->price = 20.00;
$p2->id_tax_rules_group = 2;
$p2->save();

// 2. Create a cart rule that applies specifically to Product 2
$cr = new CartRule();
$cr->code = 'SPECIFIC_DISCOUNT';
$cr->name = [1 => 'Discount Product 2'];
$cr->id_customer = 0;
$cr->date_from = date('Y-m-d H:i:s');
$cr->date_to = date('Y-m-d H:i:s', strtotime('+1 year'));
$cr->reduction_amount = 4.00;
$cr->reduction_product = 2; // Applies to Product 2
$cr->reduction_tax = 0;
$cr->quantity = 99;
$cr->quantity_per_user = 99;
$cr->active = 1;
$cr->add();

// 3. Create an Order with 1x Product 1 and 2x Product 2
$order = new Order();
$order->id_address_invoice = 1;
$order->id_address_delivery = 1;
$order->id_cart = 1;
$order->id_currency = 1;
$order->id_lang = 1;
$order->id_customer = $customer->id;
$order->id_carrier = 1;
$order->payment = 'Check';
$order->module = 'ps_checkpayment';
$order->total_paid = 70.00;
$order->total_paid_real = 70.00;
$order->total_products = 70.00; // (1*30) + (2*20)
$order->total_products_wt = 75.00;
$order->conversion_rate = 1;
$order->secure_key = Tools::passwdGen(32);
$order->total_discounts_tax_excl = 4.00; // The specific discount
$order->add();

// Link Cart Rule to Order
$ocr = new OrderCartRule();
$ocr->id_order = $order->id;
$ocr->id_cart_rule = $cr->id;
$ocr->name = $cr->name[1];
$ocr->value = 4.00;
$ocr->value_tax_excl = 4.00;
$ocr->add();

// Create Order Details
$od1 = new OrderDetail();
$od1->id_order = $order->id;
$od1->id_shop = 1;
$od1->product_id = 1;
$od1->product_name = 'Product 1';
$od1->product_quantity = 1;
$od1->unit_price_tax_excl = 30.00;
$od1->add();

$od2 = new OrderDetail();
$od2->id_order = $order->id;
$od2->id_shop = 1;
$od2->product_id = 2;
$od2->product_name = 'Product 2';
$od2->product_quantity = 2;
$od2->unit_price_tax_excl = 20.00;
$od2->add();

// 4. Execute the method under test
try {
    $taxDetails = $order->getProductTaxesDetails();
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

// 5. Check the invoice detail
// Expected: 
// Product 1 (21%): Base = 30.00
// Product 2 (10%): Base = (20 * 2) - 4.00 = 36.00
// Buggy behavior: Product 2 Base = (20 - 4) * 2 = 32.00

$baseForProduct2 = 0;
foreach ($taxDetails as $row) {
    // We identify the row for Product 2 by the tax rate (10% = 0.10)
    if (abs($row['tax'] - 0.10) < 0.001) {
        $baseForProduct2 = $row['base_amount'];
    }
}

echo "Observed base amount for 10% tax (Product 2): $baseForProduct2\n";
echo "Expected base amount: 36.00\n";

if (abs($baseForProduct2 - 36.00) < 0.001) {
    exit(0);
} else {
    exit(1);
}
