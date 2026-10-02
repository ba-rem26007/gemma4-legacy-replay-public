<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #35812, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductLazyArray;
use PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductPresentationSettings;
use PrestaShop\PrestaShop\Adapter\Presenter\Product\PriceFormatter;
use PrestaShop\PrestaShop\Adapter\Presenter\Product\ImageRetriever;
use PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductColorsRetriever;

/**
 * Test for Ticket: Unit price not working correctly outside of product page and cart
 * The bug: ProductLazyArray used 'unit_price' (which is always tax-excluded in Cart::getProducts)
 * instead of choosing between 'unit_price_tax_included' and 'unit_price_tax_excluded' 
 * based on the presentation settings.
 */

try {
    // 1. Setup Context
    $context = Context::getContext();
    $context->language = new Language(1);
    $context->currency = new Currency(1);
    $context->shop = new Shop(1);
    $context->customer = new Customer(1);
    $context->employee = new Employee(1); // Avoid "If no employee is assigned..." warning

    // 2. Setup Product with Unit Price and a Tax Group that is NOT 0%
    $p = new Product(1);
    $p->price = 10.00;
    $p->unit_price = 2.00; 
    $p->unity = 'kg';
    $p->id_tax_rules_group = 1; // Standard tax group
    $p->active = 1;
    $p->quantity = 100;
    $p->update();

    // 3. Setup Cart to get the product array as it's passed to the presenter
    $cart = new Cart();
    $cart->id_currency = 1;
    $cart->id_lang = 1;
    $cart->id_customer = 1;
    $cart->add();
    $cart->updateQty(1, 1);

    $products = $cart->getProducts();
    if (empty($products)) {
        echo "Error: Cart is empty\n";
        exit(1);
    }
    $product_data = $products[0];

    $up_tax_excl = (float)$product_data['unit_price_tax_excluded'];
    $up_tax_incl = (float)$product_data['unit_price_tax_included'];
    
    echo "Data - Unit Price Tax Excl: $up_tax_excl\n";
    echo "Data - Unit Price Tax Incl: $up_tax_incl\n";

    // CRITICAL: If tax is 0, the test cannot distinguish between the bug and the fix
    if (abs($up_tax_incl - $up_tax_excl) < 0.001) {
        echo "Error: Tax included price is equal to excluded price. Test cannot validate the fix.\n";
        exit(1);
    }

    // 4. Instantiate ProductLazyArray with dependencies
    $translator = new class implements \Symfony\Component\Translation\TranslatorInterface {
        public function trans($id, array $parameters = [], $domain = null, $locale = null) { return $id; }
        public function getLocale() { return 'fr'; }
        public function setLocale($locale) { }
        public function fallback() { }
        public function getTranslationDomain($id) { return ''; }
        public function setTranslationDomain($domain) { }
        public function getTranslations() { return []; }
        public function setTranslations($translations) { }
    };

    // Set include_taxes = true to trigger the bug (before fix, it would still show tax excl)
    $settings = new ProductPresentationSettings(true); 
    $link = new Link();
    $priceFormatter = new PriceFormatter($context->language, $context->currency, $context);
    $imageRetriever = new ImageRetriever($link);
    $productColorsRetriever = new ProductColorsRetriever($link);

    $presenter = new ProductLazyArray(
        $settings,
        $product_data,
        $context->language,
        $imageRetriever,
        $link,
        $priceFormatter,
        $productColorsRetriever,
        $translator
    );

    // Accessing 'unit_price' triggers addPriceInformation()
    $presented_unit_price = $presenter['unit_price'];

    echo "Presented Unit Price: $presented_unit_price\n";

    // 5. Assertion
    $expected_tax_incl_formatted = $priceFormatter->format($up_tax_incl);
    $expected_tax_excl_formatted = $priceFormatter->format($up_tax_excl);

    echo "Expected (Tax Incl): $expected_tax_incl_formatted\n";
    echo "Expected (Tax Excl): $expected_tax_excl_formatted\n";

    if ($presented_unit_price === $expected_tax_incl_formatted) {
        echo "SUCCESS: Presenter correctly used the tax-included unit price.\n";
        exit(0);
    } elseif ($presented_unit_price === $expected_tax_excl_formatted) {
        echo "FAILURE: Presenter used the tax-excluded unit price despite include_taxes=true.\n";
        exit(1);
    } else {
        echo "FAILURE: Presenter returned an unexpected value: $presented_unit_price\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Exception: " . $t->getMessage() . "\n";
    exit(1);
}
