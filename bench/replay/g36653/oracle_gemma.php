<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #36653, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\Cart\QueryHandler\GetCartForViewingHandler;
use PrestaShop\PrestaShop\Adapter\ImageManager;
use PrestaShop\PrestaShop\Core\Localization\Locale;
use PrestaShop\PrestaShop\Core\Domain\Cart\Query\GetCartForViewing;
use PrestaShop\PrestaShop\Core\Domain\Cart\ValueObject\CartId;

// Convert only relevant warnings to exceptions, ignore deprecations
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
        return false; 
    }
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

try {
    // Setup Context
    $context = Context::getContext();
    $context->shop = new Shop(1);
    $context->language = new Language(1);
    $context->currency = new Currency(1);

    // Setup Demo Data
    $customer = new Customer(1);
    if (!Validate::isLoadedObject($customer)) {
        $customer = new Customer();
        $customer->firstname = 'John';
        $customer->lastname = 'Doe';
        $customer->email = 'john@example.com';
        $customer->passwd = '12345678';
        $customer->add();
    }

    $address = new Address(1);
    if (!Validate::isLoadedObject($address)) {
        $address = new Address();
        $address->firstname = 'John';
        $address->lastname = 'Doe';
        $address->address1 = '123 Street';
        $address->postcode = '75000';
        $address->city = 'Paris';
        $address->id_country = 1;
        $address->add();
    }

    $product = new Product(1);
    if (!Validate::isLoadedObject($product)) {
        $product = new Product();
        $product->price = 10.0;
        $product->add();
    }

    // Create a Cart
    $cart = new Cart();
    $cart->id_customer = $customer->id;
    $cart->id_address_delivery = $address->id;
    $cart->id_address_invoice = $address->id;
    $cart->id_currency = 1;
    $cart->id_lang = 1;
    $cart->add();
    $cart->updateQty(1, 1);

    // Add customization to the product in the cart
    $cf = new CustomizationField();
    $cf->id_product = $product->id;
    $cf->type = 1; // text
    $cf->required = 0;
    $cf->name = [1 => 'Customization Name']; // Multilang field
    $cf->add();

    $cust = new Customization();
    $cust->id_cart = $cart->id;
    $cust->id_product = $product->id;
    $cust->add();

    $cd = new CustomizedData();
    $cd->id_customization = $cust->id;
    $cd->id_product_attribute = 0;
    $cd->id_customization_field = $cf->id;
    $cd->value = 'Customized Text';
    $cd->add();

    echo "Cart created with ID: {$cart->id} and customizable product 1\n";

    // Instantiate the Handler directly
    $handler = new GetCartForViewingHandler(new ImageManager(), new Locale());
    $query = new GetCartForViewing(new CartId($cart->id));

    // Execute the handler
    $result = $handler->handle($query);

    echo "Handler executed successfully.\n";
    exit(0);

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if (strpos($e->getMessage(), 'id_address_delivery') !== false) {
        echo "Bug reproduced: Undefined array key id_address_delivery\n";
        exit(1);
    }
    exit(1);
}
