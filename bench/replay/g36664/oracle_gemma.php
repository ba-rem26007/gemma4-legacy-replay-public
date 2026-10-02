<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #36664, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

try {
    $id_lang = 1;
    $id_shop = 1;

    // 1. Create an Attribute Group
    // Both group_type and name are mandatory for AttributeGroup validation
    $group = new AttributeGroup();
    $group->group_type = 'select';
    $group->name = 'PrivateGroupName'; 
    $group->add();

    // 2. Set the names for the group in the lang table (Private vs Public)
    $groupLang = new AttributeGroupLang();
    $groupLang->id_attribute_group = $group->id;
    $groupLang->id_lang = $id_lang;
    $groupLang->id_shop = $id_shop;
    $groupLang->name = 'PrivateGroupName'; // This is the private name
    $groupLang->public_name = 'PublicGroupName'; // This is the public name
    $groupLang->add();

    // 3. Create an Attribute Value for this group
    $attr = new Attribute();
    $attr->id_attribute_group = $group->id;
    $attr->add();

    $attrLang = new AttributeLang();
    $attrLang->id_attribute = $attr->id;
    $attrLang->id_lang = $id_lang;
    $attrLang->id_shop = $id_shop;
    $attrLang->name = 'ValueRed';
    $attrLang->add();

    // 4. Create a Combination for Product 1 (Product 1 exists in demo data)
    $comb = new Combination();
    $comb->id_product = 1;
    $comb->add();
    // Link combination to the attribute
    $comb->setAttributes([$attr->id => 1]);

    // 5. Execute the code touched by the fix
    $product = new Product(1);
    $anchor = $product->getAnchor($comb->id);

    echo "Generated Anchor: $anchor\n";

    // The anchor is processed by Tools::str2url()
    $expected_public = Tools::str2url('PublicGroupName');
    $expected_private = Tools::str2url('PrivateGroupName');

    $has_public = strpos($anchor, $expected_public) !== false;
    $has_private = strpos($anchor, $expected_private) !== false;

    echo "Contains Public Name: " . ($has_public ? 'YES' : 'NO') . "\n";
    echo "Contains Private Name: " . ($has_private ? 'YES' : 'NO') . "\n";

    // The test passes if the public name is present and the private name is NOT.
    if ($has_public && !$has_private) {
        echo "SUCCESS: Public attribute name is used in the URL anchor.\n";
        exit(0);
    } else {
        echo "FAILURE: URL anchor still contains private name or is missing public name.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
