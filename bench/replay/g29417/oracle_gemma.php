<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29417, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Model\Product\AdminModelAdapter;
use PrestaShop\PrestaShop\Adapter\LegacyContext;
use PrestaShop\PrestaShop\Adapter\Product\AdminProductWrapper;
use PrestaShop\PrestaShop\Adapter\Tools;
use PrestaShop\PrestaShop\Adapter\Product\ProductDataProvider;
use PrestaShop\PrestaShop\Adapter\Supplier\SupplierDataProvider;
use PrestaShop\PrestaShop\Adapter\Warehouse\WarehouseDataProvider;
use PrestaShop\PrestaShop\Adapter\Feature\FeatureDataProvider;
use PrestaShop\PrestaShop\Adapter\Pack\PackDataProvider;
use PrestaShop\PrestaShop\Adapter\Shop\Context as ShopContext;
use PrestaShop\PrestaShop\Adapter\Tax\TaxRuleDataProvider;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RequestContext;
use PrestaShopBundle\Utils\FloatParser;

try {
    // Setup context
    $context = Context::getContext();
    $context->shop = new Shop(1);
    $context->language = new Language(1);
    $context->currency = new Currency(1);

    // Demo data: Product 1
    $product = new Product(1);
    if (!Validate::isLoadedObject($product)) {
        $p = new Product();
        $p->price = 10;
        $p->name = [1 => 'Test Product'];
        $p->link_rewrite = [1 => 'test-product'];
        $p->add();
        $product = $p;
    }

    // Instantiate dependencies for AdminModelAdapter
    $legacyContext = new LegacyContext();
    $shopContext = new ShopContext();
    
    // AdminProductWrapper requires: Product, array (data), and LegacyContext
    $wrapper = new AdminProductWrapper($product, [], $legacyContext);
    
    $tools = new Tools();
    $productDataProvider = new ProductDataProvider();
    $supplierDataProvider = new SupplierDataProvider();
    $warehouseDataProvider = new WarehouseDataProvider();
    $featureDataProvider = new FeatureDataProvider();
    $packDataProvider = new PackDataProvider();
    $taxRuleDataProvider = new TaxRuleDataProvider();
    $router = new Router(new RouteCollection(), new RequestContext());
    $floatParser = new FloatParser();

    $adapter = new AdminModelAdapter(
        $legacyContext,
        $wrapper,
        $tools,
        $productDataProvider,
        $supplierDataProvider,
        $warehouseDataProvider,
        $featureDataProvider,
        $packDataProvider,
        $shopContext,
        $taxRuleDataProvider,
        $router,
        $floatParser
    );

    // Data that should be preserved when $isMultiShopContext = true
    $formData = [
        'additional_delivery_times' => 'Specific delivery time for all shops',
        'additional_shipping_cost' => '15.00',
    ];

    // The bug: when $isMultiShopContext is true, these fields are stripped 
    // if they are not in the allowed shop fields list in AdminModelAdapter.
    $result = $adapter->getModelData($formData, true);

    $hasDeliveryTimes = isset($result['additional_delivery_times']);
    $hasShippingCost = isset($result['additional_shipping_cost']);

    echo "additional_delivery_times present: " . ($hasDeliveryTimes ? 'YES' : 'NO') . "\n";
    echo "additional_shipping_cost present: " . ($hasShippingCost ? 'YES' : 'NO') . "\n";

    if ($hasDeliveryTimes && $hasShippingCost) {
        exit(0);
    } else {
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Exception: " . $t->getMessage() . "\n";
    exit(1);
}
