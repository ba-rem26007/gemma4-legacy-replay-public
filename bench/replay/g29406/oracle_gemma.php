<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29406, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

try {
    // 1. Create a Supplier
    $supplier = new Supplier();
    $supplier->name = 'Test Supplier';
    $supplier->active = 1;
    $supplier->add();
    $id_supplier = (int)$supplier->id;
    echo "Supplier created: ID $id_supplier\n";

    // 2. Create a Product
    $product = new Product();
    $product->price = 10.00;
    $product->name = [1 => 'Test Product'];
    $product->link_rewrite = [1 => 'test-product'];
    $product->id_category_default = 2;
    $product->active = 1;
    $product->add();
    $id_product = (int)$product->id;
    echo "Product created: ID $id_product\n";

    // 3. Associate Supplier to Product (Main product supplier)
    // We must explicitly set id_product_attribute to 0 to avoid "est vide" validation error
    $ps = new ProductSupplier();
    $ps->id_product = $id_product;
    $ps->id_supplier = $id_supplier;
    $ps->id_product_attribute = 0; 
    $ps->add();
    echo "Supplier associated with product (id_product_attribute = 0)\n";

    // 4. Prepare data for combination import
    // The controller uses these keys to identify the product and the attributes to create
    $info = [
        'id_product' => $id_product,
        'attribute' => 'Color:color:0',
        'value' => 'Blue:0',
        'quantity' => 10,
    ];

    $groups = [];
    $attributes = [];
    $regenerate = false;
    $shop_is_feature_active = true;
    $validateOnly = false;
    $default_language = 1;

    // 5. Execute the import logic using Reflection to access protected method
    $controller = new AdminImportController();
    $controller->context = $context;

    $reflection = new ReflectionClass('AdminImportController');
    $method = $reflection->getMethod('attributeImportOne');
    $method->setAccessible(true);

    $method->invokeArgs($controller, [
        $info,
        $default_language,
        $groups,
        $attributes,
        $regenerate,
        $shop_is_feature_active,
        $validateOnly
    ]);
    echo "Combination import executed\n";

    // 6. Verify if the combination was created
    $sql_comb = 'SELECT id_product_attribute FROM ' . _DB_PREFIX_ . 'product_attribute WHERE id_product = ' . (int)$id_product;
    $id_product_attribute = (int)Db::getInstance()->getValue($sql_comb);

    if ($id_product_attribute <= 0) {
        echo "Error: Combination was not created\n";
        exit(1);
    }
    echo "Combination created: ID $id_product_attribute\n";

    // 7. Check if the supplier was cloned to the combination
    // The fix should have created a ProductSupplier entry where id_product_attribute = $id_product_attribute
    $sql_supp = 'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'product_supplier WHERE id_product = ' . (int)$id_product . ' AND id_product_attribute = ' . (int)$id_product_attribute;
    $supplier_count = (int)Db::getInstance()->getValue($sql_supp);

    echo "Combination Supplier Count: $supplier_count\n";

    // The test passes if the supplier from the main product was cloned to the combination
    exit($supplier_count > 0 ? 0 : 1);

} catch (\Throwable $t) {
    echo "Exception: " . $t->getMessage() . "\n";
    exit(1);
}
