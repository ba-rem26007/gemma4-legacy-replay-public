<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29186, validé pre/post automatiquement
require 'config/config.inc.php';

try {
    // Setup Context
    $context = Context::getContext();
    $context->shop = new Shop(1);
    $context->language = new Language(1);
    $context->currency = new Currency(1);
    
    $employee = new Employee(1);
    if (!$employee->id) {
        $employee->lastname = 'Test';
        $employee->firstname = 'Test';
        $employee->email = 'test@test.com';
        $employee->passwd = 'passwd';
        $employee->add();
    }
    $context->employee = $employee;

    // Use a fresh product to avoid demo data pollution
    $product = new Product();
    $product->price = 10;
    $product->id_category_default = 2;
    $product->name = [1 => 'Test Product'];
    $product->link_rewrite = [1 => 'test-product'];
    $product->add();
    $id_product = $product->id;

    // Create a combination
    $comb = new Combination();
    $comb->id_product = $id_product;
    $comb->reference = 'REF-COMB';
    $comb->price = 0;
    $comb->weight = 0;
    $comb->wholesale_price = 0;
    $comb->add();
    $id_product_attribute = $comb->id;

    // Create Suppliers
    $s1 = new Supplier();
    $s1->name = 'Supplier 1';
    $s1->add();
    $id_supplier_1 = $s1->id;

    $s2 = new Supplier();
    $s2->name = 'Supplier 2';
    $s2->add();
    $id_supplier_2 = $s2->id;

    // Create ProductSupplier associations
    // 1. Main supplier (id_product_attribute = 0)
    $ps_main = new ProductSupplier();
    $ps_main->id_product = $id_product;
    $ps_main->id_supplier = $id_supplier_1;
    $ps_main->id_product_attribute = 0;
    $ps_main->add();

    // 2. Combination supplier (id_product_attribute > 0)
    $ps_comb = new ProductSupplier();
    $ps_comb->id_product = $id_product;
    $ps_comb->id_supplier = $id_supplier_2;
    $ps_comb->id_product_attribute = $id_product_attribute;
    $ps_comb->add();

    echo "Setup: Product $id_product with Main Supplier $id_supplier_1 (attr 0) and Combination Supplier $id_supplier_2 (attr $id_product_attribute)\n";

    // Mock the request: we want to keep only the combination supplier.
    // This forces the deletion of the main supplier (attr 0).
    $_POST['suppliers_to_associate'] = [$id_supplier_2];
    $_POST['id_product'] = $id_product;

    // Instantiate Controller
    $controller = new AdminProductsController();
    $controller->context = $context;
    $controller->object = $product;

    // Execute the target method
    $controller->processSuppliers($id_product);

    // Verification
    // According to the fix: if the main supplier (attr 0) is deleted, 
    // ALL combination suppliers (attr > 0) for this product must also be deleted.
    // Even the one we tried to keep ($id_supplier_2) should be gone.
    $check_comb = Db::getInstance()->getValue('
        SELECT COUNT(*) 
        FROM ' . _DB_PREFIX_ . 'product_supplier 
        WHERE id_product = ' . (int)$id_product . ' 
        AND id_product_attribute > 0'
    );

    echo "Combination suppliers remaining: $check_comb\n";

    // The test passes if the combination supplier was purged along with the main one
    exit($check_comb == 0 ? 0 : 1);

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
