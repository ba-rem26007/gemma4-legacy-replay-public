<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #32061, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Wrapper to access protected properties and methods of CartController
 */
class TestCartController extends CartController
{
    public function setParams($id_product, $qty)
    {
        $this->id_product = (int)$id_product;
        $this->qty = (int)$qty;
        $this->id_product_attribute = 0;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}

// 1. Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);
$context->customer = new Customer(1);

// 2. Setup Product and Stock
$id_product = 1;
$product = new Product($id_product);
if (!Validate::isLoadedObject($product)) {
    $product = new Product();
    $product->id = $id_product;
    $product->price = 10.0;
    $product->name = [1 => 'Test Product'];
    $product->link_rewrite = [1 => 'test-product'];
    $product->save();
}
$product->price = 10.0;
$product->save();

// Set available stock to 3
StockAvailable::setQuantity($id_product, 0, 3);

// 3. Setup Cart
$cart = new Cart();
$cart->id_customer = $context->customer->id;
$cart->id_lang = $context->language->id;
$cart->id_currency = $context->currency->id;
$cart->add();

// Add 5 items to cart (exceeds stock of 3)
$cart->updateQty(5, $id_product, 0);
$context->cart = $cart;

// 4. Simulate request to reduce quantity to 2 (which is <= stock 3)
// We set 'update' to trigger processChangeProductInCart and 'mode' to 'update' for the fix
$_GET['update'] = 1;
$_GET['mode'] = 'update';
$_GET['id_product'] = $id_product;
$_GET['qty'] = 2;
$_GET['token'] = 'test_token';

$controller = new TestCartController();
// The constructor of FrontController already assigns Context::getContext() to $this->context
$controller->setParams($id_product, 2);

try {
    // Execute the logic
    $controller->postProcess();
    
    $errors = $controller->getErrors();
    $final_qty = Cart::getQuantity($cart->id, $id_product);
    
    echo "Observed errors count: " . count($errors) . "\n";
    if (!empty($errors)) {
        echo "First error: " . $errors[0] . "\n";
    }
    echo "Final quantity in cart: $final_qty\n";

    // BUG: Before fix, reducing quantity from 5 to 2 when stock is 3 
    // triggers an availability error and prevents the update.
    // FIXED: No error should be raised when reducing to a valid quantity.
    if (empty($errors) && $final_qty == 2) {
        exit(0);
    } else {
        exit(1);
    }
} catch (\Throwable $t) {
    echo "Exception: " . $t->getMessage() . "\n";
    exit(1);
}
