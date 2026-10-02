<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #30252, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

try {
    // 1. Clean and Prepare data in DB
    // We don't use Product object to avoid validation errors on 'customizable'
    Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'customized_data`');
    Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'customization`');

    // Create a customization linked to id_cart = 0
    // This simulates a customization saved when no cookie/session was present
    Db::getInstance()->execute('
        INSERT INTO `' . _DB_PREFIX_ . 'customization` 
        (`id_cart`, `id_product`, `id_address_delivery`, `quantity`, `in_cart`) 
        VALUES (0, 1, 0, 1, 0)
    ');
    $id_customization = (int)Db::getInstance()->Insert_ID();

    Db::getInstance()->execute('
        INSERT INTO `' . _DB_PREFIX_ . 'customized_data` 
        (`id_customization`, `type`, `index`, `value`) 
        VALUES (' . $id_customization . ', 1, 1, "Shared Secret Value")
    ');

    // 2. Simulate a request without cookies/session
    // We instantiate a Cart object but we do NOT call add(), so $cart->id remains 0.
    $cart = new Cart();
    echo "Cart ID: " . (int)$cart->id . "\n";

    // 3. Call the method targeted by the fix
    // Before fix: the SQL query is executed. Since $this->id is 0, it matches the row with id_cart = 0.
    // After fix: the method returns [] immediately because (int)$this->id === 0.
    $customizations = $cart->getProductCustomization(1);

    $count = count($customizations);
    echo "Number of customizations found for empty cart: $count\n";

    if ($count > 0) {
        echo "BUG: Customizations were leaked from the database to an uninitialized cart.\n";
        exit(1);
    }

    echo "SUCCESS: No customizations leaked.\n";
    exit(0);

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
