<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29821, validé pre/post automatiquement
require 'config/config.inc.php';

try {
    // 1. Setup: Use existing demo data
    $cat = new Category(2);
    $cat->active = 1;
    $cat->update();

    $p = new Product(1);
    $p->id_category_default = 2;
    $p->update();
    $p->addToCategories([2]);

    // 2. Restrict the category to a group that the visitor is NOT in.
    // Visitor is typically in Group 1. We restrict to Group 3 (Customer).
    $cat->cleanGroups();
    $cat->addGroupsIfNoExist(3);

    // 3. Disable the "Customer groups" feature in Performance settings.
    // This is the trigger for the fix.
    Configuration::updateValue('PS_CUSTOMER_GROUPS', 0);

    // 4. Test Category access for a visitor (id_customer = 0)
    // Before fix: returns false because the visitor (Group 1) is not in the category's allowed groups (Group 3).
    // After fix: returns true immediately because Group::isFeatureActive() is false.
    $catAccess = $cat->checkAccess(0);
    echo "Category access for visitor (feature disabled): " . ($catAccess ? 'ALLOWED' : 'DENIED') . "\n";

    // 5. Test Product access for a visitor (id_customer = 0)
    // Before fix: returns false because it checks the restricted category.
    // After fix: returns true immediately because Group::isFeatureActive() is false.
    $prodAccess = Product::checkAccessStatic(1, 0);
    echo "Product access for visitor (feature disabled): " . ($prodAccess ? 'ALLOWED' : 'DENIED') . "\n";

    // The test passes if both are allowed despite the group restriction
    if ($catAccess && $prodAccess) {
        exit(0);
    } else {
        echo "Bug still present: Access is still restricted even when feature is disabled.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
