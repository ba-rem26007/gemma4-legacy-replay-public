<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #34873, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\EntityMapper;

try {
    // Use a real PrestaShop class to avoid "Identifier or table format not valid" errors
    // Category is a standard ObjectModel that exists in all installations.
    $entity = new Category();
    
    // We simulate the bug by defining a field that is both TYPE_BOOL and translatable.
    // In the real database, 'name' is a string, but EntityMapper uses the definition 
    // provided in $entity_defs to decide how to cast the value.
    $entity_defs = [
        'classname' => 'Category',
        'table' => 'category',
        'primary' => 'id_category',
        'multilang' => true,
        'fields' => [
            'name' => [
                'type' => \ObjectModel::TYPE_BOOL, 
                'lang' => true
            ],
        ],
    ];

    $mapper = new EntityMapper();
    
    // Passing $id_lang = null triggers the logic in EntityMapper::load that 
    // fetches all language translations into an array.
    $mapper->load(
        1,      // id_category 1 exists in demo data
        null,   // id_lang = null triggers the loading of all languages (array)
        $entity, 
        $entity_defs, 
        1,      // id_shop
        false   // should_cache_objects
    );

    echo "Value of entity->name: " . (is_array($entity->name) ? 'Array' : $entity->name) . "\n";

    // BEFORE FIX: $entity->name becomes the string "Array" because of (string) $value
    // AFTER FIX: $entity->name remains an array of values
    if (is_array($entity->name)) {
        echo "Success: Field is an array.\n";
        exit(0);
    } else {
        echo "Failure: Field was cast to string (likely 'Array').\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
