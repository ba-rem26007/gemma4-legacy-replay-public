<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #31752, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);

// Ensure the shop has a default category to fallback to
// We use a known category from demo data (2..9)
$shopDefaultCat = 2;
$context->shop->id_category = $shopDefaultCat;

try {
    // We use an existing product (1) to avoid Product->add() which 
    // triggers a crash due to missing columns (meta_keywords) in the environment's ps_product_lang.
    $idProduct = 1;
    $p = new Product($idProduct);

    if (!Validate::isLoadedObject($p)) {
        echo "Product 1 not found in demo data.\n";
        exit(1);
    }

    // Force the default category to NULL in the database to trigger the bug.
    // We use Db::getInstance()->execute() because Product->update() would trigger the same 
    // schema error as Product->add().
    Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'product` SET `id_category_default` = NULL WHERE `id_product` = ' . (int)$idProduct);
    Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'product_shop` SET `id_category_default` = NULL WHERE `id_product` = ' . (int)$idProduct . ' AND `id_shop` = 1');

    echo "Product $idProduct category set to NULL in DB.\n";
    echo "Shop default category ID: $shopDefaultCat\n";

    // Call the method under test
    $result = $p->getDefaultCategory();

    echo "Value returned by getDefaultCategory(): " . var_export($result, true) . "\n";
    echo "Type returned: " . gettype($result) . "\n";

    // The fix ensures the return value is always an integer.
    // Before the fix, it returned an array ['id_category_default' => ...] when the DB value was missing.
    if (is_int($result)) {
        if ($result === $shopDefaultCat) {
            echo "Success: Method returned the correct fallback category ID as an integer.\n";
            exit(0);
        } else {
            echo "Failure: Method returned an integer but not the expected fallback ID ($result instead of $shopDefaultCat).\n";
            exit(1);
        }
    } else {
        echo "Failure: Method returned " . gettype($result) . " instead of an integer.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
