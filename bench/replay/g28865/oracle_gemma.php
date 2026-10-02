<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #28865, validé pre/post automatiquement
require 'config/config.inc.php';

$context = Context::getContext();
$context->language = new Language(1);

// 1. Setup: Use SQL to bypass ObjectModel validation and ensure a clean multishop state
$db = Db::getInstance();

// Ensure ShopGroup 1 exists
$db->execute('INSERT INTO `ps_shop_group` (`id_shop_group`, `name`, `active`) 
              VALUES (1, "Group 1", 1) 
              ON DUPLICATE KEY UPDATE `name`="Group 1"');

// Ensure Shop 2 exists (linked to Group 1, Category 1)
$db->execute('INSERT INTO `ps_shop` (`id_shop`, `id_shop_group`, `id_category`, `name`, `active`) 
              VALUES (2, 1, 1, "Shop 2", 1) 
              ON DUPLICATE KEY UPDATE `name`="Shop 2"');

// Setup Category 2 with different names for Shop 1 and Shop 2
// Primary key for ps_category_lang is (id_category, id_shop, id_lang)
$db->execute('INSERT INTO `ps_category_lang` (`id_category`, `id_shop`, `id_lang`, `name`) 
              VALUES (2, 1, 1, "Main Shop Category") 
              ON DUPLICATE KEY UPDATE `name`="Main Shop Category"');
$db->execute('INSERT INTO `ps_category_lang` (`id_category`, `id_shop`, `id_lang`, `name`) 
              VALUES (2, 2, 1, "Secondary Shop Category") 
              ON DUPLICATE KEY UPDATE `name`="Secondary Shop Category"');

// 2. Set context to the second shop
$context->shop = new Shop(2);

try {
    // 3. Use PrestaShopCollection to fetch the category
    // The bug: PrestaShopCollection joins ps_category_lang but doesn't filter by id_shop.
    // This results in multiple rows (one per shop) being returned for the same category.
    $collection = new PrestaShopCollection('Category', 1);
    $collection->where('id_category', '=', 2);
    $results = $collection->getAll();

    $count = count($results);
    $observed_name = 'NOT FOUND';
    if ($count > 0) {
        // ObjectModel::hydrateCollection is called with id_lang=1, 
        // so $results[0]->name is a string, not an array.
        $observed_name = $results[0]->name;
    }

    echo "Current Shop ID: " . (int)$context->shop->id . "\n";
    echo "Results count: $count\n";
    echo "Observed name: $observed_name\n";

    // EXPECTED BEHAVIOR (Corrected):
    // - Only 1 result should be returned (the one for Shop 2).
    // - The name should be "Secondary Shop Category".
    // BUGGY BEHAVIOR (Before fix):
    // - Multiple results are returned (Shop 1 and Shop 2).
    // - The first result is typically the one from the main shop (Shop 1).
    if ($count === 1 && $observed_name === 'Secondary Shop Category') {
        exit(0);
    } else {
        echo "FAIL: Expected 1 result with name 'Secondary Shop Category', but got $count results with name '$observed_name'\n";
        exit(1);
    }
} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
