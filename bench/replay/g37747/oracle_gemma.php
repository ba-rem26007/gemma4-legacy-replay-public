<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37747, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->controller = new stdClass();
$context->controller->controller_type = 'front';
$context->controller->php_self = 'index.php'; // Fix for Hook.php warning

// Setup Manufacturer
$m = new Manufacturer();
$m->name = 'Test Manufacturer';
$m->active = 1;
$m->add();
$idManufacturer = $m->id;

// Setup Product (using demo product 1)
$p = new Product(1);
$p->id_manufacturer = $idManufacturer;
$p->price = 10.0;
$p->active = 1;
$p->visibility = 'both';
$p->save();

// Setup Sales data in ps_product_sale
// Based on the previous error 'ps_ps_product_sale', the insert method in this environment 
// seems to add the prefix automatically. We pass the table name without _DB_PREFIX_.
Db::getInstance()->insert('product_sale', [
    'id_product' => (int)$p->id,
    'quantity' => 10,
    'sale_nbr' => 1,
    'date_upd' => date('Y-m-d')
]);

try {
    /**
     * Trigger the bug: sorting by 'sales' on Manufacturer page.
     * 
     * Before fix: $alias becomes 'p.', resulting in "ORDER BY p.`sales`", 
     * which throws a SQL exception because the 'sales' column doesn't exist in ps_product.
     * 
     * After fix: $alias becomes '', resulting in "ORDER BY `sales`", 
     * which refers to the alias "psales.`quantity` as sales" created by the new JOIN.
     */
    $result = Manufacturer::getProducts(
        (int)$idManufacturer,
        1, // idLang
        1, // p (page)
        10, // n (limit)
        'sales', // orderBy
        'DESC' // orderWay
    );

    if ($result === false) {
        echo "Manufacturer::getProducts returned false (SQL error likely)\n";
        exit(1);
    }

    echo "Success: Manufacturer::getProducts returned " . count($result) . " products\n";
    exit(0);

} catch (\Throwable $e) {
    echo "Exception caught: " . $e->getMessage() . "\n";
    exit(1);
}
