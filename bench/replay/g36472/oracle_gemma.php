<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #36472, validé pre/post automatiquement
require 'config/config.inc.php';

try {
    // 1. Setup Global Context
    $context = Context::getContext();
    $context->shop = new Shop(1);
    $context->language = new Language(1);
    $context->currency = new Currency(1);
    $context->customer = new Customer(1);
    $context->link = new Link();

    // 2. Setup Data
    $id_product = 1;
    $product = new Product($id_product, false, 1);
    if (!Validate::isLoadedObject($product)) {
        echo "Product 1 not found\n";
        exit(1);
    }

    // Create a cart and add the product with a specific quantity
    $cart = new Cart();
    $cart->id_currency = 1;
    $cart->id_lang = 1;
    $cart->id_customer = $context->customer->id;
    $cart->add();
    
    $quantity_in_cart = 5;
    $cart->updateQty($quantity_in_cart, $id_product);
    
    $context->cart = $cart;

    // 3. Simulate Request
    $_GET['id_product'] = (string)$id_product;

    // 4. Instantiate ProductController with a complete mock for the Symfony container
    // We override the get() method to return a dummy object for ANY service requested.
    // This prevents the "ObjectPresenter" error which occurs when the real Symfony 
    // presenters are called in a CLI environment without a full container.
    $controller = new class extends ProductController {
        public function get($id) {
            return new class {
                public function addParams($p) { return $this; }
                public function present() { return []; }
            };
        }
    };

    // Initialize the product object inside the controller
    // This sets $this->product based on $_GET['id_product']
    $controller->getProduct();

    // 5. Execute the target method
    $product_vars = $controller->getTemplateVarProduct();

    $observed_qty = isset($product_vars['cart_quantity']) ? (int)$product_vars['cart_quantity'] : -1;

    echo "Expected cart_quantity: $quantity_in_cart\n";
    echo "Observed cart_quantity: $observed_qty\n";

    // The bug: $product['id_product'] is used instead of $this->product->id.
    // Product::getProductProperties() returns an array where the ID is in the key 'id', not 'id_product'.
    // Therefore, $product['id_product'] is null, and getProductQuantity(null, ...) returns 0.
    if ($observed_qty === $quantity_in_cart) {
        exit(0);
    } else {
        echo "Bug detected: cart_quantity is $observed_qty instead of $quantity_in_cart\n";
        exit(1);
    }

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
