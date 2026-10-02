<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #32631, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\Presenter\Cart\CartPresenter;

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);
$context->employee = new Employee(1);

// Correct implementation of TranslatorInterface to avoid Fatal Error
// Signature: trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
$context->translator = new class implements \Symfony\Contracts\Translation\TranslatorInterface {
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string {
        return $id;
    }
};

try {
    // Use demo customer 1
    $customer = new Customer(1);

    // Create Cart 1 with Product 1
    $cart1 = new Cart();
    $cart1->id_currency = 1;
    $cart1->id_lang = 1;
    $cart1->id_customer = $customer->id;
    $cart1->add();
    $cart1->updateQty(1, 1);

    // Create Cart 2 with Product 2
    $cart2 = new Cart();
    $cart2->id_currency = 1;
    $cart2->id_lang = 1;
    $cart2->id_customer = $customer->id;
    $cart2->add();
    $cart2->updateQty(2, 1);

    // Clear PrestaShop static cache to ensure we are testing the presenter's cache logic
    Cache::clean();

    $presenter = new CartPresenter();

    // First call: caches the result for 'presentedCart_0' (before fix) or 'presentedCart_0[id]' (after fix)
    $res1 = $presenter->present($cart1, false);
    $idProd1 = isset($res1['products'][0]['id_product']) ? (int)$res1['products'][0]['id_product'] : null;

    // Second call: should NOT return the cached result of Cart 1
    $res2 = $presenter->present($cart2, false);
    $idProd2 = isset($res2['products'][0]['id_product']) ? (int)$res2['products'][0]['id_product'] : null;

    echo "Cart 1 presented product ID: $idProd1\n";
    echo "Cart 2 presented product ID: $idProd2\n";

    if ($idProd1 === null || $idProd2 === null) {
        echo "Error: Could not retrieve product IDs from presenter\n";
        exit(1);
    }

    // If the bug is present, $idProd2 will be equal to $idProd1 because of the cache collision
    if ($idProd1 === $idProd2) {
        echo "BUG DETECTED: Cart 2 is using Cart 1's cached presentation.\n";
        exit(1);
    }

    echo "SUCCESS: Cart 2 has its own presentation.\n";
    exit(0);

} catch (\Throwable $t) {
    echo "Exception: " . $t->getMessage() . "\n";
    exit(1);
}
