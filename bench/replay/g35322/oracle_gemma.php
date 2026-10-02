<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #35322, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\Presenter\Order\OrderDetailLazyArray;

/**
 * We define the missing classes in their namespaces to satisfy the type hints 
 * of the OrderDetailLazyArray constructor, as the Symfony container is 
 * unavailable in CLI and the autoloader might fail for these specific presenters.
 */
if (!class_exists('PrestaShop\PrestaShop\Adapter\Presenter\Locale')) {
    class_alias(new class {
        public function formatPrice($amount, $currencyIso) {
            return 'Formatted ' . $amount;
        }
    }, 'PrestaShop\PrestaShop\Adapter\Presenter\Locale');
}

// Since we cannot use class_alias for a class that doesn't exist yet in a way 
// that satisfies a type hint, we define the class manually if it's missing.
if (!class_exists('PrestaShop\PrestaShop\Adapter\Presenter\Locale')) {
    // This is a trick to define a class in a namespace dynamically
    eval('namespace PrestaShop\PrestaShop\Adapter\Presenter { 
        class Locale { 
            public function formatPrice($amount, $currencyIso) { return "Formatted " . $amount; } 
        } 
    }');
}

if (!interface_exists('Symfony\Component\Translation\TranslatorInterface')) {
    eval('namespace Symfony\Component\Translation { 
        interface TranslatorInterface { 
            public function trans($id, array $parameters = [], $domain = null, $locale = null); 
        } 
    }');
}

if (!class_exists('PrestaShop\PrestaShop\Adapter\Presenter\Translator')) {
    eval('namespace PrestaShop\PrestaShop\Adapter\Presenter { 
        class Translator implements \Symfony\Component\Translation\TranslatorInterface { 
            public function trans($id, array $parameters = [], $domain = null, $locale = null) { return $id; } 
        } 
    }');
}

try {
    // Setup Context
    Context::getContext()->shop = new Shop(1);
    Context::getContext()->language = new Language(1);
    Context::getContext()->currency = new Currency(1);

    // Use existing Order 1
    $order = new Order(1);
    
    /**
     * BUG LOGIC:
     * tax_calculation_method = 0 (Tax included)
     * Before fix: (!$order->getTaxCalculationMethod()) is true -> returns shipping_cost_tax_excl
     * After fix: ($order->getTaxCalculationMethod()) is false -> returns shipping_cost_tax_incl
     */
    $order->tax_calculation_method = 0;
    $order->save();

    // Ensure OrderCarrier has distinct values for tax excl and incl
    // shipping_cost = Tax Excl, total_shipping = Tax Incl
    Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.'order_carrier WHERE id_order = '.(int)$order->id);
    Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'order_carrier (id_order, id_carrier, shipping_cost, total_shipping) VALUES ('.(int)$order->id.', 1, 10.00, 12.00)');

    // Instantiate dependencies
    $locale = new \PrestaShop\PrestaShop\Adapter\Presenter\Locale();
    $translator = new \PrestaShop\PrestaShop\Adapter\Presenter\Translator();

    // Instantiate the presenter
    $presenter = new OrderDetailLazyArray($order, $locale, $translator);
    
    // Execute the method under test
    $shippingResults = $presenter->getShipping();
    
    // Get the raw shipping data to verify expectations
    $rawShipping = $order->getShipping();
    reset($rawShipping);
    $shippingId = key($rawShipping);
    $shippingData = current($rawShipping);
    
    $actualValue = $shippingResults[$shippingId]['shipping_cost'];
    $expectedValue = 'Formatted ' . $shippingData['shipping_cost_tax_incl'];

    echo "Tax Calculation Method: " . $order->getTaxCalculationMethod() . "\n";
    echo "Shipping Cost Tax Excl: " . $shippingData['shipping_cost_tax_excl'] . "\n";
    echo "Shipping Cost Tax Incl: " . $shippingData['shipping_cost_tax_incl'] . "\n";
    echo "Observed: $actualValue\n";
    echo "Expected: $expectedValue\n";

    // The test passes if the observed value is the tax-included price (Formatted 12.00)
    exit($actualValue === $expectedValue ? 0 : 1);

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
