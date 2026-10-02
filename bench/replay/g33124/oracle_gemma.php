<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33124, validé pre/post automatiquement
require 'config/config.inc.php';

$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

try {
    // 1. Setup: Create a Tax
    // The 'name' field in Tax is translatable, so it must be an array indexed by language ID
    $tax = new Tax();
    $tax->name = [1 => 'TVA Test 20%']; 
    $tax->rate = 20;
    $tax->active = 1;
    $tax->add();

    // 2. Setup: Create a Tax Rules Group
    $trg = new TaxRulesGroup();
    $trg->name = 'TRG Test';
    $trg->active = 1;
    $trg->add();

    // 3. Setup: Create a Tax Rule linking the Tax and the Group
    Db::getInstance()->execute('
        INSERT INTO ' . _DB_PREFIX_ . 'tax_rule 
        (id_tax_rules_group, id_country, id_state, zipcode_from, zipcode_to, id_tax, behavior, description) 
        VALUES (
            ' . (int)$trg->id . ', 
            1, 
            0, 
            "", 
            "", 
            ' . (int)$tax->id . ', 
            1, 
            "Test Rule"
        )
    ');

    echo "Tax created: {$tax->id}, TRG created: {$trg->id}\n";

    // Verify rule exists initially
    $ruleExistsBefore = Db::getInstance()->getValue('
        SELECT id_tax_rule FROM ' . _DB_PREFIX_ . 'tax_rule 
        WHERE id_tax = ' . (int)$tax->id . ' AND id_tax_rules_group = ' . (int)$trg->id
    );
    if (!$ruleExistsBefore) {
        echo "Error: Initial tax rule was not created.\n";
        exit(1);
    }

    // 4. Action: Disable the tax
    // In the old code, Tax::update() calls _onStatusChange() which deletes the TaxRule if active = 0
    echo "Disabling tax...\n";
    $tax->active = 0;
    $tax->update();

    // 5. Action: Enable the tax
    echo "Enabling tax...\n";
    $tax->active = 1;
    $tax->update();

    // 6. Verification: Check if the tax rule still exists
    $ruleExistsAfter = Db::getInstance()->getValue('
        SELECT id_tax_rule FROM ' . _DB_PREFIX_ . 'tax_rule 
        WHERE id_tax = ' . (int)$tax->id . ' AND id_tax_rules_group = ' . (int)$trg->id
    );

    if ($ruleExistsAfter) {
        echo "Success: Tax rule was preserved after toggle.\n";
        exit(0);
    } else {
        echo "Failure: Tax rule was deleted during the toggle process.\n";
        exit(1);
    }

} catch (\Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    exit(1);
}
