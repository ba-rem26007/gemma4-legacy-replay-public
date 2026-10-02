<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33043, validé pre/post automatiquement
require 'config/config.inc.php';

try {
    // 1. Setup Context
    $context = Context::getContext();
    $context->id_lang = 1;
    $context->id_shop = 1;
    $context->currency = new Currency(1);

    // 2. Setup Product: 11.90 HT -> 14.28 TTC (assuming 20% tax)
    $p = new Product();
    $p->price = 11.90;
    $p->id_tax_rules_group = 1; 
    $p->name = [1 => 'Mug Test'];
    $p->link_rewrite = [1 => 'mug-test'];
    $p->add();

    // 3. Setup Specific Price: -10.00 TTC
    $sp = new SpecificPrice();
    $sp->id_product = (int)$p->id;
    $sp->id_shop = 1;
    $sp->id_currency = 1;
    $sp->id_country = 1;
    $sp->id_group = 0;
    $sp->id_customer = 0;
    $sp->from_quantity = 1;
    $sp->price = 11.90; 
    $sp->reduction = 10.00;
    $sp->reduction_type = 'amount';
    $sp->reduction_tax_include = 1;
    $sp->from = '0000-00-00 00:00:00';
    $sp->to = '0000-00-00 00:00:00';
    $sp->add();

    // 4. Setup Group with 50% discount
    $g = new Group();
    $g->name = [1 => 'Discount Group'];
    $g->reduction = 50.00;
    $g->add();

    // 5. Setup Customer in that group
    $c = new Customer();
    $c->lastname = 'Test';
    $c->firstname = 'Test';
    $c->email = 'test' . uniqid() . '@test.com';
    $c->passwd = Tools::encrypt('123456');
    $c->id_default_group = (int)$g->id;
    $c->add();
    $context->customer = $c;

    // 6. Setup Cart
    $cart = new Cart();
    $cart->id_customer = (int)$c->id;
    $cart->id_currency = 1;
    $cart->id_lang = 1;
    $cart->add();
    $cart->updateQty(1, (int)$p->id);

    // 7. Execute Presenter
    $presenter = new \PrestaShop\PrestaShop\Adapter\Presenter\Cart\CartPresenter();
    $presentedCart = $presenter->present($cart);

    if (!isset($presentedCart['products'][0])) {
        echo "Error: Product not found in presented cart\n";
        exit(1);
    }

    $product = $presentedCart['products'][0];
    $discountDisplay = $product['discount_amount_to_display'];

    echo "Product Price (TTC): 14.28\n";
    echo "Specific Price: -10.00 TTC\n";
    echo "Group Discount: 50%\n";
    echo "Expected Calculation: (14.28 - 10) * 0.5 = 2.14 final price. Total Discount = 14.28 - 2.14 = 12.14\n";
    echo "Observed discount_amount_to_display: $discountDisplay\n";

    // Before fix: displays only the specific price reduction (contains '10')
    // After fix: displays the real total reduction (contains '12' and '14')
    if (strpos($discountDisplay, '12') !== false && strpos($discountDisplay, '14') !== false) {
        exit(0);
    } elseif (strpos($discountDisplay, '10') !== false) {
        echo "Bug detected: only specific price reduction is displayed.\n";
        exit(1);
    } else {
        echo "Unexpected discount value.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Exception: " . $t->getMessage() . "\n";
    exit(1);
}
