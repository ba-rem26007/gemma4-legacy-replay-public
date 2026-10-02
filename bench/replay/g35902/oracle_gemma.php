<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #35902, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Proxy class to access protected methods of ProductController
 */
class ProductControllerTest extends ProductController
{
    public function publicGetRequiredQuantity($product)
    {
        return $this->getRequiredQuantity($product);
    }

    public function publicGetTemplateVarProduct()
    {
        return $this->getTemplateVarProduct();
    }
}

try {
    // 1. Setup: Product with minimum quantity = 3
    $id_product = 1;
    $p = new Product($id_product);
    $p->minimal_quantity = 3;
    $p->price = 10.0;
    $p->save();

    // IMPORTANT: PrestaShop often uses StockAvailable for the actual minimal quantity
    // If we don't set this, getProductMinimalQuantity() might return 1 or 0.
    StockAvailable::setMinimumQuantity($id_product, 0, 3);

    // 2. Setup: Customer and Cart
    $customer = new Customer(1);
    $cart = new Cart();
    $cart->id_currency = 1;
    $cart->id_lang = 1;
    $cart->id_customer = $customer->id;
    $cart->add();
    
    // Add 3 units to the cart to satisfy the minimum quantity
    $cart->updateQty(3, $id_product, 0);

    // 3. Setup Context
    $context = Context::getContext();
    $context->cart = $cart;
    $context->language = new Language(1);
    $context->shop = new Shop(1);
    $context->employee = new Employee(1);

    // 4. Instantiate Controller
    $controller = new ProductControllerTest();
    $controller->product = $p;
    $controller->context = $context;

    // 5. Execute: getTemplateVarProduct
    // This method populates the array used by getRequiredQuantity
    $product_var = $controller->publicGetTemplateVarProduct();

    $min_qty_observed = (int)$product_var['minimal_quantity'];
    echo "Observed minimal_quantity in array: $min_qty_observed\n";

    if ($min_qty_observed === 0) {
        echo "Setup Error: minimal_quantity is 0, the test cannot detect the bug.\n";
        exit(1);
    }

    if (isset($product_var['cart_quantity'])) {
        echo "Observed cart_quantity in array: " . $product_var['cart_quantity'] . "\n";
    } else {
        echo "Observed cart_quantity in array: NOT SET (Expected before fix)\n";
    }

    // 6. Execute: getRequiredQuantity
    // Before fix: returns minimal_quantity (3) because it ignores cart content.
    // After fix: returns 0 because cart_quantity (3) >= minimal_quantity (3).
    $required_qty = $controller->publicGetRequiredQuantity($product_var);
    echo "Required Qty (result): $required_qty\n";

    // ASSERTION:
    // The bug is that required_qty remains 3 even if the cart already has 3.
    // The fix makes it return 0.
    if ($required_qty === 0) {
        echo "SUCCESS: Required quantity is 0 when minimum is already reached in cart.\n";
        exit(0);
    } else {
        echo "FAIL: Required quantity is $required_qty, but should be 0.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "ERROR: " . $t->getMessage() . "\n";
    exit(1);
}
