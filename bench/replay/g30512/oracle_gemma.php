<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #30512, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Model\Product\AdminModelAdapter;

// Setup context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

try {
    // Ensure product 1 exists for the adapter to work with
    $product = new Product(1);
    if (!Validate::isLoadedObject($product)) {
        $product = new Product();
        $product->price = 10;
        $product->name = [1 => 'Test Product'];
        $product->link_rewrite = [1 => 'test-product'];
        $product->add();
    }

    // Use Reflection to instantiate AdminModelAdapter without its constructor
    $ref = new ReflectionClass('PrestaShopBundle\Model\Product\AdminModelAdapter');
    $adapter = $ref->newInstanceWithoutConstructor();

    // Mock FloatParser
    $floatParserMock = new class {
        public function fromString($value) {
            return (float)$value;
        }
    };
    $fpProp = $ref->getProperty('floatParser');
    $fpProp->setAccessible(true);
    $fpProp->setValue($adapter, $floatParserMock);

    // Mock ProductDataProvider to avoid "linkRewrite() on null"
    $productDataProviderMock = new class($product) {
        private $product;
        public function __construct($product) { $this->product = $product; }
        public function getProduct($id) { return $this->product; }
    };
    $pdProp = $ref->getProperty('productAdapter');
    $pdProp->setAccessible(true);
    $pdProp->setValue($adapter, $productDataProviderMock);

    // Initialize translatableKeys
    $tkProp = $ref->getProperty('translatableKeys');
    $tkProp->setAccessible(true);
    $tkProp->setValue($adapter, []);

    // Prepare form data with all keys required by getModelData to avoid warnings and crashes
    $form_data = [
        'id_product' => 1,
        'step1' => [
            'type_product' => 0,
            'redirect_type' => 0,
        ],
        'step2' => [
            'categories' => [],
            'tree' => [],
        ],
        'step3' => [
            'combinations' => [
                [
                    'attribute_quantity' => '-10',
                    'attribute_unity' => '1',
                    'attribute_wholesale_price' => '5',
                    'attribute_price' => '0',
                    'attribute_weight' => '0',
                ]
            ]
        ],
        'step4' => [],
        'step5' => [],
        'step6' => [
            'display_options' => [],
        ],
    ];

    // Execute the method
    $result = $adapter->getModelData($form_data);
    
    if (!isset($result['combinations'][0]['attribute_quantity'])) {
        echo "Error: attribute_quantity not found in result\n";
        exit(1);
    }

    $observedQty = $result['combinations'][0]['attribute_quantity'];
    echo "Observed quantity: $observedQty\n";

    // The bug: abs() was applied to attribute_quantity, turning -10 into 10.
    // The fix: remove abs().
    if ($observedQty == -10) {
        echo "Success: Negative quantity preserved.\n";
        exit(0);
    } else {
        echo "Failure: Negative quantity was converted to positive (abs() still present).\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Exception: " . $t->getMessage() . "\n";
    echo "File: " . $t->getFile() . " line " . $t->getLine() . "\n";
    exit(1);
}
