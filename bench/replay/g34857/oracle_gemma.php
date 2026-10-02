<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #34857, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Helper class to expose the protected productImportOne method for testing
 */
class TestImportController extends AdminImportController
{
    public function publicProductImportOne($info, $default_language_id, $id_lang, $force_ids, $regenerate, $shop_is_feature_active, $shop_ids, $match_ref, &$accessories, $validateOnly = false)
    {
        return $this->productImportOne($info, $default_language_id, $id_lang, $force_ids, $regenerate, $shop_is_feature_active, $shop_ids, $match_ref, $accessories, $validateOnly);
    }
}

try {
    // 1. Initialize Context to avoid AdminController crashes
    $context = Context::getContext();
    $context->employee = new Employee(1);
    $context->shop = new Shop(1);
    $context->language = new Language(1);
    $context->currency = new Currency(1);

    // 2. Setup Data
    $p = new Product();
    $p->reference = 'IMPORT_TEST_REF_100';
    $p->price = 10.0;
    $p->id_category_default = 2;
    $p->active = 1;
    $p->add();

    // Manually link to category 2 to ensure initial state
    Db::getInstance()->insert('category_product', [
        'id_category' => 2,
        'id_product' => (int)$p->id
    ]);

    echo "Product created: ID {$p->id}, Ref {$p->reference}\n";
    
    // Verification helper using SQL to avoid cache issues with Product::getCategories()
    $getCats = function($id_prod) {
        $res = Db::getInstance()->executeS('SELECT id_category FROM '._DB_PREFIX_.'category_product WHERE id_product = '.(int)$id_prod);
        return array_column($res, 'id_category');
    };

    echo "Initial categories: " . implode(', ', $getCats($p->id)) . "\n";

    // 3. Prepare Import Simulation
    $controller = new TestImportController();
    $controller->separator = ',';

    // The bug: if multiple categories are provided in the CSV, 
    // the old code skips all but the first one because $product->category 
    // becomes an array after the first iteration, triggering the 'continue'.
    $info = [
        'reference' => 'IMPORT_TEST_REF_100',
        'categories' => '3,4', // We want to add categories 3 and 4
    ];

    $accessories = [];
    $default_lang = 1;
    $id_lang = 1;
    $force_ids = 0;
    $regenerate = 0;
    $shop_active = 1;
    $shop_ids = [1];
    $match_ref = 1; // Match by reference

    // 4. Execute the import logic
    $controller->publicProductImportOne(
        $info,
        $default_lang,
        $id_lang,
        $force_ids,
        $regenerate,
        $shop_active,
        $shop_ids,
        $match_ref,
        $accessories,
        false
    );

    // 5. Verification
    $final_categories = $getCats($p->id);
    echo "Final categories after import: " . implode(', ', $final_categories) . "\n";

    // Expected: categories 3 and 4 should both be present.
    // Bugged behavior: Only category 3 is added (or nothing if the logic fails).
    $has_cat_3 = in_array(3, $final_categories);
    $has_cat_4 = in_array(4, $final_categories);

    if ($has_cat_3 && $has_cat_4) {
        echo "SUCCESS: Both categories 3 and 4 were correctly imported.\n";
        exit(0);
    } else {
        echo "FAILURE: One or more categories missing. Bug still present.\n";
        echo "Cat 3: " . ($has_cat_3 ? 'YES' : 'NO') . ", Cat 4: " . ($has_cat_4 ? 'YES' : 'NO') . "\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
    exit(1);
}
