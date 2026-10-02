<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #38168, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
Shop::setContext(Shop::CONTEXT_SHOP);

try {
    $rootId = (int)Configuration::get('PS_ROOT_CATEGORY');
    $homeId = 2; // Based on demo data categories 2..9

    // Ensure Home category exists and is at level 1
    $home = new Category($homeId);
    if (!Validate::isLoadedObject($home)) {
        $home = new Category();
        $home->name = [1 => 'Home'];
        $home->link_rewrite = [1 => 'home'];
        $home->active = 1;
        $home->level_depth = 1;
        $home->id_parent = $rootId;
        $home->add();
        $homeId = (int)$home->id;
    }

    // Create an extra category at level 1 to trigger the bug:
    // count(Category::getCategoriesWithoutParent()) will be > 1
    $extra = new Category();
    $extra->name = [1 => 'Extra Root Level'];
    $extra->link_rewrite = [1 => 'extra-root-level'];
    $extra->active = 1;
    $extra->level_depth = 1;
    $extra->id_parent = $rootId;
    $extra->add();

    // Create a child category under the Home category
    $child = new Category();
    $child->name = [1 => 'Child Category'];
    $child->link_rewrite = [1 => 'child-category'];
    $child->active = 1;
    $child->id_parent = $homeId;
    $child->add();

    echo "Root Category ID: $rootId\n";
    echo "Home Category ID: $homeId\n";
    echo "Extra Category ID: " . (int)$extra->id . "\n";
    echo "Child Category ID: " . (int)$child->id . "\n";

    // Trigger the method
    // In Shop context, it should stop at the Home category and NOT include the Root category.
    $parents = $child->getParentsCategories(1);

    $foundRoot = false;
    if (is_array($parents)) {
        foreach ($parents as $p) {
            if (isset($p['id_category']) && (int)$p['id_category'] === $rootId) {
                $foundRoot = true;
                break;
            }
        }
    }

    echo "Root category found in parents: " . ($foundRoot ? 'YES' : 'NO') . "\n";

    // If the bug is present, the Root category is included because the shop context's 
    // id_category was forced to PS_ROOT_CATEGORY.
    // If fixed, it should not be found.
    exit($foundRoot ? 1 : 0);

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
