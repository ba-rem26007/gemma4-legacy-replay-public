<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29548, validé pre/post automatiquement
require 'config/config.inc.php';

// Context setup
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

try {
    // 1. Prepare data: Product 1 already exists in demo data
    $id_product = 1;
    $product = new Product($id_product, false, 1);
    
    // 2. Create a specific tag and associate it with the product
    $tagName = 'regression_tag_' . time();
    $tag = new Tag();
    $tag->name = $tagName;
    $tag->id_lang = 1;
    if (!$tag->add()) {
        echo "Failed to create tag\n";
        exit(1);
    }
    
    $tag->setProducts([$id_product]);
    echo "Tag '$tagName' created and associated with product $id_product\n";

    // 3. Manually populate the search index using raw SQL to avoid any automatic 
    // prefixing issues in the Db::insert method (avoiding ps_ps_ errors).
    $word_lower = strtolower($tagName);
    Db::getInstance()->execute('INSERT INTO ' . _DB_PREFIX_ . 'search_word (`word`, `id_lang`) VALUES ("' . pSQL($word_lower) . '", 1)');
    
    $id_word = (int)Db::getInstance()->getValue('
        SELECT id_word 
        FROM ' . _DB_PREFIX_ . 'search_word 
        WHERE word = "' . pSQL($word_lower) . '"'
    );

    if ($id_word === 0) {
        echo "Failed to retrieve search_word ID\n";
        exit(1);
    }

    Db::getInstance()->execute('INSERT INTO ' . _DB_PREFIX_ . 'search_index (`id_word`, `id_product`) VALUES (' . (int)$id_word . ', ' . (int)$id_product . ')');
    
    echo "Search index manually populated for word ID $id_word and product $id_product\n";

    // 4. Delete the tag
    // BEFORE FIX: Only the 'ps_tag' record is deleted. 'ps_search_index' remains.
    // AFTER FIX: Tag::delete() calls Search::removeProductsSearchIndex(), cleaning 'ps_search_index'.
    $tag->delete();
    echo "Tag deleted\n";

    // 5. Check if the product is still present in the search index for this word
    $still_indexed = (int)Db::getInstance()->getValue('
        SELECT COUNT(*) 
        FROM ' . _DB_PREFIX_ . 'search_index 
        WHERE id_word = ' . (int)$id_word . ' AND id_product = ' . (int)$id_product
    );

    echo "Products still in search index for deleted tag: $still_indexed\n";

    // If still_indexed > 0, the bug is present (exit 1). If 0, it's fixed (exit 0).
    exit($still_indexed === 0 ? 0 : 1);

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
