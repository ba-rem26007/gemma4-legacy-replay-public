<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33048, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\EntityMapper;

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

try {
    // 1. Create a Cart Rule using ObjectModel to ensure all required fields are filled
    $cr = new CartRule();
    $cr->code = 'TAX_TEST_' . time();
    $cr->name = [1 => 'Tax Test Rule'];
    $cr->id_currency = 1;
    $cr->id_customer_group = 1;
    $cr->id_group = 1;
    $cr->tax_include = 1; // The field we are testing
    $cr->quantity = 100;
    $cr->quantity_per_user = 100;
    $cr->date_from = date('Y-m-d H:i:s');
    $cr->date_to = date('Y-m-d H:i:s', strtotime('+1 year'));
    $cr->active = 1;
    if (!$cr->add()) {
        throw new \Exception("Failed to create CartRule");
    }

    // Ensure shop association
    Db::getInstance()->insert('cart_rule_shop', [
        'id_cart_rule' => (int)$cr->id,
        'id_shop' => 1
    ]);

    // 2. Prepare the EntityMapper and the target entity
    $mapper = new EntityMapper();
    
    // Use stdClass to avoid any ObjectModel magic and verify direct property assignment
    $entity = new \stdClass();
    
    // The EntityMapper uses the table name provided here. 
    // Based on previous validation, the system adds the prefix automatically.
    // So 'cart_rule' becomes 'ps_cart_rule'.
    $entity_defs = [
        'classname' => 'CartRule',
        'table' => 'cart_rule', 
        'primary' => 'id_cart_rule',
        'fields' => [
            'tax_include' => [
                'type' => \ObjectModel::TYPE_BOOL,
            ],
        ],
    ];

    // 3. Call the load method
    $mapper->load(
        (int)$cr->id, 
        1, 
        $entity, 
        $entity_defs, 
        1, 
        false
    );

    // Check if the property was actually set
    if (!isset($entity->tax_include)) {
        echo "Error: tax_include property was not set on the entity. Query might have returned no results.\n";
        exit(1);
    }

    $value = $entity->tax_include;
    $type = gettype($value);

    echo "CartRule ID: " . $cr->id . "\n";
    echo "tax_include value: " . $value . "\n";
    echo "tax_include type: " . $type . "\n";

    // The fix: fields of type TYPE_BOOL must be cast to (string)
    // Before fix: type is 'integer' (from DB)
    // After fix: type is 'string'
    if ($type === 'string') {
        exit(0);
    } else {
        echo "Error: tax_include should be a string (fixed) but is " . $type . " (buggy).\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Exception: " . $t->getMessage() . "\n";
    echo "File: " . $t->getFile() . " Line: " . $t->getLine() . "\n";
    exit(1);
}
