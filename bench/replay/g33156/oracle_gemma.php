<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33156, validé pre/post automatiquement
require 'config/config.inc.php';

try {
    $context = Context::getContext();
    $context->shop = new Shop(1);
    $context->language = new Language(1);
    $context->currency = new Currency(1);
    $context->customer = new Customer(1);

    // We create a NEW product to guarantee it has NO attributes.
    // If we use Product(1), it might have attributes, which would make 
    // checkAllProductsAreStillAvailableInThisState() return false even 
    // before the fix, masking the bug.
    $p = new Product();
    $p->price = 10.00;
    $p->active = 1;
    $p->available_for_order = 1;
    $p->name = [1 => 'Non-Regression Product'];
    $p->link_rewrite = [1 => 'non-regression-product'];
    $p->id_category_default = 2;
    $p->add();

    // Create a cart and add this simple product
    $c = new Cart();
    $c->id_currency = 1;
    $c->id_lang = 1;
    $c->id_customer = 1;
    $c->add();
    $c->updateQty(1, $p->id);

    // Now disable the product
    $p->active = 0;
    $p->save();

    // We instantiate a fresh Cart object to avoid internal cache of getProducts()
    $c_fresh = new Cart($c->id);
    $res = $c_fresh->checkAllProductsAreStillAvailableInThisState();

    echo "Product ID: " . (int)$p->id . "\n";
    echo "Product active: " . (int)$p->active . "\n";
    echo "Cart checkAllProductsAreStillAvailableInThisState: " . ($res ? 'true' : 'false') . "\n";

    // BEFORE FIX: For a simple product, the method returns true (BUG).
    // AFTER FIX: The method returns false because the product is inactive (CORRECTED).
    exit($res === false ? 0 : 1);

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
