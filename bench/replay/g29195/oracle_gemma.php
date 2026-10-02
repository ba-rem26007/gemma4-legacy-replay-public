<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29195, validé pre/post automatiquement
require 'config/config.inc.php';

try {
    // 1. Setup Context & Multistore
    $context = Context::getContext();
    $context->employee = new Employee(1);
    $context->language = new Language(1);
    $context->shop = new Shop(1);

    Configuration::updateValue('PS_MULTISHOP_FEATURE_ACTIVE', 1);
    Shop::setContext(Shop::CONTEXT_ALL);

    // 2. Create a second shop
    $shop2 = new Shop();
    $shop2->id_shop_group = 1;
    $shop2->id_category = 2; 
    $shop2->name = 'Test Shop 2';
    $shop2->active = 1;
    $shop2->add();
    $id_shop2 = (int)$shop2->id;

    // 3. Create a product
    $p = new Product();
    $p->price = 10.0;
    $p->active = 0; // Start as disabled
    $p->add();
    $id_product = (int)$p->id;

    // Ensure the product is associated with both shops in the product_shop table
    // This is necessary because we are testing the update of the 'active' state across shops.
    Db::getInstance()->execute('INSERT IGNORE INTO ' . _DB_PREFIX_ . 'product_shop (id_product, id_shop, active) VALUES (' . $id_product . ', 1, 0)');
    Db::getInstance()->execute('INSERT IGNORE INTO ' . _DB_PREFIX_ . 'product_shop (id_product, id_shop, active) VALUES (' . $id_product . ', ' . $id_shop2 . ', 0)');

    // 4. Simulate the BO form submission
    // AdminController::loadObject() often looks at $_GET for the ID
    $_GET['id_product'] = $id_product;
    $_POST['id_product'] = $id_product;
    $_POST['active'] = 1;
    // multishop_check contains the IDs of the shops selected in the BO
    $_POST['multishop_check'] = [1, $id_shop2];

    // Instantiate the controller
    $controller = new AdminProductsController();
    
    // We must ensure the controller's object is loaded and the context is correct
    $controller->context = $context;

    // Call the method containing the fix
    // This method calls copyFromPost() and then $this->object->update()
    $controller->processUpdate();

    // 5. Verification
    // The fix adds 'state' (Product::STATE_SAVED) to the fields to update in the shop table.
    // In PrestaShop's Product object, 'state' is often mapped to 'active'.
    $active_shop1 = Db::getInstance()->getValue('
        SELECT active FROM ' . _DB_PREFIX_ . 'product_shop 
        WHERE id_product = ' . $id_product . ' AND id_shop = 1'
    );
    $active_shop2 = Db::getInstance()->getValue('
        SELECT active FROM ' . _DB_PREFIX_ . 'product_shop 
        WHERE id_product = ' . $id_product . ' AND id_shop = ' . $id_shop2
    );

    echo "Product active in Shop 1: $active_shop1\n";
    echo "Product active in Shop 2: $active_shop2\n";

    if ($active_shop1 == 1 && $active_shop2 == 1) {
        exit(0);
    } else {
        echo "Error: Product is not active in Shop 2. The multishop update failed to propagate the state.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Exception: " . $t->getMessage() . "\n";
    exit(1);
}
