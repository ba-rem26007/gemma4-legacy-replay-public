<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #38552, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\Presenter\Order\OrderLazyArray;
use PrestaShop\PrestaShop\Adapter\Presenter\Cart\CartPresenter;
use PrestaShop\PrestaShop\Adapter\Presenter\Cart\CartLazyArray;

// --- Setup Context ---
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);
Context::getContext()->currency = new Currency(1);

// --- Mock Classes ---
class MockCartPresenter extends CartPresenter
{
    public function present($cart, bool $shouldSeparateGifts = false): CartLazyArray
    {
        // We return a CartLazyArray containing two different customizations for the same product.
        // In a real scenario, a module would have modified the images/attributes for these specific customizations.
        return new CartLazyArray([
            'products' => [
                [
                    'id_product' => 1,
                    'id_product_attribute' => 0,
                    'id_customization' => 10,
                    'attributes' => 'CustomAttr10',
                ],
                [
                    'id_product' => 1,
                    'id_product_attribute' => 0,
                    'id_customization' => 20,
                    'attributes' => 'CustomAttr20',
                ],
            ]
        ]);
    }
}

try {
    // --- Data Setup ---
    // Use existing Customer 1 to avoid password validation errors
    $customer = new Customer(1);
    if (!Validate::isLoadedObject($customer)) {
        $customer = new Customer();
        $customer->firstname = 'Test';
        $customer->lastname = 'User';
        $customer->email = 'test@example.com';
        $customer->passwd = Tools::encrypt('password');
        $customer->add();
    }

    // Create a Cart
    $cart = new Cart();
    $cart->id_customer = $customer->id;
    $cart->id_currency = 1;
    $cart->id_lang = 1;
    $cart->id_address_delivery = 1;
    $cart->id_address_invoice = 1;
    $cart->add();

    // Create an Order
    $order = new Order();
    $order->id_address_invoice = 1;
    $order->id_address_delivery = 1;
    $order->id_currency = 1;
    $order->id_lang = 1;
    $order->id_customer = $customer->id;
    $order->id_cart = $cart->id;
    $order->id_carrier = 1;
    $order->payment = 'Check';
    $order->module = 'ps_checkpayment';
    $order->total_paid = 20;
    $order->total_paid_real = 20;
    $order->total_products = 20;
    $order->total_products_wt = 20;
    $order->conversion_rate = 1;
    $order->add();

    // Create two OrderDetails for the same product but different customizations.
    // OrderLazyArray::getProducts() calls $order->getProducts(), which uses OrderDetail::getList().
    $od1 = new OrderDetail();
    $od1->id_order = $order->id;
    $od1->product_id = 1;
    $od1->product_attribute_id = 0;
    $od1->id_customization = 10;
    $od1->product_quantity = 1;
    $od1->product_name = 'Product 1 - Cust 10';
    $od1->product_price = 10;
    $od1->total_price = 10;
    $od1->add();

    $od2 = new OrderDetail();
    $od2->id_order = $order->id;
    $od2->product_id = 1;
    $od2->product_attribute_id = 0;
    $od2->id_customization = 20;
    $od2->product_quantity = 1;
    $od2->product_name = 'Product 1 - Cust 20';
    $od2->product_price = 10;
    $od2->total_price = 10;
    $od2->add();

    // --- Execution ---
    $mockPresenter = new MockCartPresenter();
    $priceFormatter = SymfonyContainer::getInstance()->get('prestashop.adapter.presenter.price_formatter');
    
    $lazyArray = new OrderLazyArray($order, $mockPresenter, $priceFormatter);
    $products = $lazyArray->getProducts();

    echo "Order Product 0 attributes: " . ($products[0]['attributes'] ?? 'null') . "\n";
    echo "Order Product 1 attributes: " . ($products[1]['attributes'] ?? 'null') . "\n";

    // --- Validation ---
    // Before fix: $products[0] and $products[1] both match the first cart product (id_product 1)
    // and get 'CustomAttr10' because id_customization is ignored in the matching loop.
    // After fix: $products[0] matches id_cust 10 and $products[1] matches id_cust 20.
    if (isset($products[0]['attributes']) && $products[0]['attributes'] === 'CustomAttr10' && 
        isset($products[1]['attributes']) && $products[1]['attributes'] === 'CustomAttr20') {
        echo "SUCCESS: Customizations are correctly matched.\n";
        exit(0);
    } else {
        echo "FAILURE: Customizations are not correctly matched. Both likely have the same attributes.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
