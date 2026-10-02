<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #31330, validé pre/post automatiquement
require 'config/config.inc.php';

try {
    // 1. Setup: Create a restricted profile (no permissions)
    $profileRestricted = new Profile();
    $profileRestricted->name = [1 => 'Restricted Profile'];
    $profileRestricted->add();

    // 2. Setup: Create a restricted employee
    $employeeRestricted = new Employee();
    $employeeRestricted->id_profile = $profileRestricted->id;
    $employeeRestricted->id_lang = 1; // Required field
    $employeeRestricted->firstname = 'Restricted';
    $employeeRestricted->lastname = 'User';
    $employeeRestricted->email = 'restricted@example.com';
    $employeeRestricted->passwd = 'password123';
    $employeeRestricted->active = 1;
    $employeeRestricted->add();

    // 3. Setup: Create a QuickAccess entry for adding a product
    // The fix targets NEW_PRODUCT_LINK or NEW_PRODUCT_V2_LINK
    $linkToTest = 'controller=' . QuickAccess::NEW_PRODUCT_LINK;
    $qa = new QuickAccess();
    $qa->link = $linkToTest;
    $qa->new_window = 0;
    $qa->name = [1 => 'Add Product Test'];
    $qa->add();

    // 4. Set Context for the restricted employee
    $context = Context::getContext();
    $context->employee = $employeeRestricted;
    $context->shop = new Shop(1);
    $context->language = new Language(1);

    echo "Testing QuickAccess for restricted employee (ID: {$employeeRestricted->id})...\n";

    // 5. Execute the code touched by the fix
    // This method now checks Access::isGranted('ROLE_MOD_TAB_ADMINPRODUCTS_CREATE', $context->employee->id_profile)
    $quickAccesses = QuickAccess::getQuickAccessesWithToken(1, $employeeRestricted->id);

    // 6. Diagnosis
    $found = false;
    if (is_array($quickAccesses)) {
        foreach ($quickAccesses as $item) {
            if (isset($item['link'])) {
                // The fix removes the item if the user has no permission.
                // We check if the resulting link contains the product creation identifiers.
                if (strpos($item['link'], 'AdminProducts') !== false || 
                    strpos($item['link'], QuickAccess::NEW_PRODUCT_V2_LINK) !== false) {
                    $found = true;
                    break;
                }
            }
        }
    }

    echo "Product creation link found in QuickAccess: " . ($found ? 'YES' : 'NO') . "\n";

    // The bug is that the link IS displayed. 
    // The fix is that the link is NOT displayed (found === false).
    if ($found) {
        echo "FAILURE: Restricted user can still see the product creation link.\n";
        exit(1);
    } else {
        echo "SUCCESS: Restricted user cannot see the product creation link.\n";
        exit(0);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
