<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #26764, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Spy class to capture Smarty assignments since we are in CLI
 */
class SmartySpy {
    public $assigned = [];
    public function assign($var, $val = null) {
        if (is_array($var)) {
            foreach ($var as $k => $v) {
                $this->assigned[$k] = $v;
            }
        } else {
            $this->assigned[$var] = $val;
        }
    }
}

try {
    // Setup Context
    $context = Context::getContext();
    $context->language = new Language(1);
    $context->shop = new Shop(1);
    $context->currency = new Currency(1);
    
    // Replace Smarty with our spy to capture the 'groups' variable
    $spy = new SmartySpy();
    $context->smarty = $spy;

    // 1. Create Attribute Groups
    $agColor = new AttributeGroup();
    $agColor->group_type = 'color';
    $agColor->name = [1 => 'Color'];
    $agColor->public_name = [1 => 'Color'];
    $agColor->add();
    $idAgColor = $agColor->id;

    $agSize = new AttributeGroup();
    $agSize->group_type = 'select';
    $agSize->name = [1 => 'Size'];
    $agSize->public_name = [1 => 'Size'];
    $agSize->add();
    $idAgSize = $agSize->id;

    // 2. Create Attributes
    $attrOrange = new Attribute();
    $attrOrange->id_attribute_group = $idAgColor;
    $attrOrange->name = [1 => 'Orange'];
    $attrOrange->add();
    $idAttrOrange = $attrOrange->id;

    $attrS = new Attribute();
    $attrS->id_attribute_group = $idAgSize;
    $attrS->name = [1 => 'S'];
    $attrS->add();
    $idAttrS = $attrS->id;

    $attrM = new Attribute();
    $attrM->id_attribute_group = $idAgSize;
    $attrM->name = [1 => 'M'];
    $attrM->add();
    $idAttrM = $attrM->id;

    // 3. Create Product
    $product = new Product();
    $product->price = 10.0;
    $product->add();
    $idProduct = $product->id;

    // 4. Create Combinations
    // Combination 1: Orange + S -> Stock -2
    $comb1 = new Combination();
    $comb1->id_product = $idProduct;
    $comb1->add();
    $comb1->setAttributes([$idAgColor => [$idAttrOrange], $idAgSize => [$idAttrS]]);
    StockAvailable::setQuantity($idProduct, $comb1->id, -2);

    // Combination 2: Orange + M -> Stock 1
    $comb2 = new Combination();
    $comb2->id_product = $idProduct;
    $comb2->add();
    $comb2->setAttributes([$idAgColor => [$idAttrOrange], $idAgSize => [$idAttrM]]);
    StockAvailable::setQuantity($idProduct, $comb2->id, 1);

    // 5. Execute the logic in ProductController
    $controller = new ProductController();
    $controller->product = $product;
    $controller->context = $context;

    // Use Reflection to call the protected method assignAttributesGroups
    $method = new ReflectionMethod('ProductController', 'assignAttributesGroups');
    $method->setAccessible(true);
    $method->invoke($controller);

    // The method assigns the 'groups' array to Smarty
    $groups = $spy->assigned['groups'] ?? [];

    // The bug: if negative stock is summed, Orange total = -2 + 1 = -1 (Hidden)
    // The fix: max(-2, 0) + max(1, 0) = 1 (Visible)
    $observedQty = 0;
    if (isset($groups[$idAgColor]['attributes_quantity'][$idAttrOrange])) {
        $observedQty = $groups[$idAgColor]['attributes_quantity'][$idAttrOrange];
    }

    echo "Attribute Orange total quantity observed: $observedQty\n";

    // The attribute should be visible (qty > 0) because one combination has stock 1
    exit($observedQty > 0 ? 0 : 1);

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
