<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #28639, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Global Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);

// Use demo customer 1
$customer = new Customer(1);
if (!Validate::isLoadedObject($customer)) {
    $customer = new Customer();
    $customer->firstname = 'Test';
    $customer->lastname = 'Customer';
    $customer->email = 'test@example.com';
    $customer->passwd = 'password123';
    $customer->add();
}
$context->customer = $customer;

// Use demo order 1
$order = new Order(1);
if (!Validate::isLoadedObject($order)) {
    $order = new Order();
    $order->id_address_invoice = 1;
    $order->id_cart = 1;
    $order->id_currency = 1;
    $order->id_lang = 1;
    $order->id_customer = (int)$customer->id;
    $order->id_carrier = 1;
    $order->payment = 'Cash';
    $order->module = 'ps_checkpayment';
    $order->total_paid = 10;
    $order->total_paid_real = 10;
    $order->total_products = 1;
    $order->total_products_wt = 10;
    $order->conversion_rate = 1;
    $order->add();
}

// Clear any existing messages for this order to avoid false positives
Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'customer_message WHERE id_order = ' . (int)$order->id);
Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'customer_thread WHERE id_order = ' . (int)$order->id);

// Simulate the form submission with a message containing only spaces
$msgValue = '   '; 
$_POST = [];
$_GET = [];
$_REQUEST = [];

$_POST['submitMessage'] = '1';
$_POST['id_order'] = (int)$order->id;
$_POST['msgText'] = $msgValue;
$_POST['id_product'] = 1;

// Ensure Tools::getValue picks up the value correctly
$observedMsg = Tools::getValue('msgText');
if ($observedMsg !== $msgValue) {
    echo "DIAGNOSTIC: Tools::getValue('msgText') returned '" . var_export($observedMsg, true) . "' instead of '   '\n";
    exit(1);
}

$controller = new OrderDetailController();

try {
    $controller->postProcess();
} catch (\Throwable $e) {
    echo "Exception caught: " . $e->getMessage() . "\n";
    exit(1);
}

// Check if a message was actually created in the database
$messageCreated = (bool)Db::getInstance()->getValue('
    SELECT id_customer_message 
    FROM ' . _DB_PREFIX_ . 'customer_message 
    WHERE id_order = ' . (int)$order->id
);

$errors = $controller->errors;
$hasBlankMessageError = false;
foreach ($errors as $error) {
    if (strpos($error, 'The message cannot be blank') !== false) {
        $hasBlankMessageError = true;
        break;
    }
}

echo "Input msgText: '   '\n";
echo "Message created in DB: " . ($messageCreated ? 'Yes' : 'No') . "\n";
echo "Blank message error detected: " . ($hasBlankMessageError ? 'Yes' : 'No') . "\n";

/**
 * BUG: 
 * - empty('   ') is false.
 * - No error is added ($hasBlankMessageError = false).
 * - The code proceeds to create a CustomerMessage in the DB ($messageCreated = true).
 * 
 * FIX:
 * - empty(trim('   ')) is true.
 * - Error "The message cannot be blank" is added ($hasBlankMessageError = true).
 * - The code does NOT create a CustomerMessage ($messageCreated = false).
 */

if ($hasBlankMessageError && !$messageCreated) {
    // Corrected behavior
    exit(0);
} else {
    // Buggy behavior: either no error was thrown, or a message was created despite spaces
    exit(1);
}
