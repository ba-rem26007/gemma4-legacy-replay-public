<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37267, validé pre/post automatiquement
require 'config/config.inc.php';

$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);
$context->employee = new Employee(1); // Avoid "If no employee is assigned" error

// 1. Setup Product: Base price 100
$p = new Product(1);
$p->price = 100.00;
$p->save();

// 2. Setup Specific Price: 20% reduction (Price becomes 80)
Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.'specific_price WHERE id_product = 1');
$sp = new SpecificPrice();
$sp->id_product = 1;
$sp->id_shop = 1;
$sp->id_currency = 1;
$sp->id_country = 1;
$sp->id_group = 1;
$sp->id_customer = 0;
$sp->from_quantity = 1;
$sp->price = 100.00; 
$sp->reduction = 0.20;
$sp->reduction_type = 'percentage';
$sp->from = date('Y-m-d H:i:s', strtotime('-1 day'));
$sp->to = date('Y-m-d H:i:s', strtotime('+1 day'));
$sp->add();

// 3. Setup Cart Rule: 10% reduction, restricted to Product 1
$cr = new CartRule();
$cr->name = [1 => 'Test Rule'];
$cr->date_from = date('Y-m-d H:i:s', strtotime('-1 day'));
$cr->date_to = date('Y-m-d H:i:s', strtotime('+1 day'));
$cr->reduction_percent = 10;
$cr->reduction_product = 1; // IMPORTANT: Rule applies to specific products
$cr->active = 1;
$cr->quantity = 100;
$cr->quantity_per_user = 100;
$cr->add();

// Create the product restriction for the Cart Rule
$rule = new CartRuleProductRule();
$rule->id_cart_rule = $cr->id;
$rule->operator = 'equals';
$rule->add();

$val = new CartRuleProductRuleValue();
$val->id_product_rule = $rule->id;
$val->value = (string)$p->id;
$val->add();

// 4. Setup Cart and add the product
$c = new Cart();
$c->id_currency = 1;
$c->id_lang = 1;
$c->id_customer = 1;
$c->add();
$c->updateQty(1, 1); 
$context->cart = $c;

/**
 * Logic:
 * Base Price = 100
 * Specific Price (20% off) = 80
 * Cart Rule (10% off on Product 1)
 * 
 * Before fix:
 * getContextualValue(false) uses $product['price'] (100)
 * Result: 100 * 10% = 10
 * 
 * After fix:
 * getContextualValue(false) uses $product['price_with_reduction_without_tax'] (80)
 * Result: 80 * 10% = 8
 */

// use_cache = false to ensure we hit the logic
$val = $cr->getContextualValue(false, $context, null, null, false);

echo "Product Base Price: 100\n";
echo "Specific Price Reduction: 20% (Price: 80)\n";
echo "Cart Rule Reduction: 10% on Product 1\n";
echo "Expected Value (after fix): 8\n";
echo "Observed Value: $val\n";

exit((float)$val == 8.0 ? 0 : 1);
