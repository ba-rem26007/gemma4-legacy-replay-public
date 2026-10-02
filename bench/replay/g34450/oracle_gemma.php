<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #34450, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

try {
    // 1. Create a TaxRulesGroup
    $trg = new TaxRulesGroup();
    $trg->name = 'Bug Trigger Group';
    $trg->active = 1;
    if (!$trg->add()) {
        echo "Failed to create TaxRulesGroup\n";
        exit(1);
    }
    $trgId = (int)$trg->id;

    // 2. Ensure the TaxRulesGroup is "used" to trigger historize()
    // We create a TaxRule and link a Product. 
    // PrestaShop's isUsed() typically checks ps_product, ps_product_shop, and ps_tax_rule.
    
    // Create a TaxRule
    $rule = new TaxRule();
    $rule->id_tax_rules_group = $trgId;
    $rule->id_country = 1;
    $rule->id_state = 0;
    $rule->zipcode_from = '';
    $rule->zipcode_to = '';
    $rule->id_tax = 1; // Assuming tax 1 exists in demo data
    $rule->behavior = 1;
    $rule->description = 'Test Rule';
    if (!$rule->add()) {
        echo "Failed to create TaxRule\n";
        exit(1);
    }

    // Link an existing product (Product 1 from demo data)
    $p = new Product(1);
    $p->id_tax_rules_group = $trgId;
    if (!$p->update()) {
        echo "Failed to update Product 1\n";
        exit(1);
    }

    // Verify isUsed() is actually true
    if (!$trg->isUsed()) {
        echo "Error: TaxRulesGroup::isUsed() returned false. Bug cannot be triggered.\n";
        exit(1);
    }
    echo "TaxRulesGroup ID $trgId is confirmed as used.\n";

    // 3. Update the TaxRulesGroup.
    // This triggers: update() -> isUsed() (true) -> historize()
    // Inside historize(), the query "UPDATE ps_carrier SET id_tax_rules_group = ..." is executed.
    // Since ps_carrier does NOT have this column, Db::getInstance()->execute() returns false.
    $trg->name = 'Updated Name';
    
    echo "Attempting to update TaxRulesGroup...\n";
    $result = $trg->update();

    if ($result === true) {
        echo "Update returned TRUE. The bug is fixed (the failing ps_carrier query is gone).\n";
        exit(0);
    } else {
        echo "Update returned FALSE. The bug is still present (ps_carrier query failed).\n";
        exit(1);
    }

} catch (\Throwable $e) {
    echo "Caught exception: " . $e->getMessage() . "\n";
    if (strpos($e->getMessage(), 'Unknown column \'id_tax_rules_group\'') !== false) {
        echo "Bug reproduced via exception: Unknown column 'id_tax_rules_group' in ps_carrier\n";
        exit(1);
    }
    exit(1);
}
