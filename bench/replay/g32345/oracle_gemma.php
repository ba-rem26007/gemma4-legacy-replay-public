<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #32345, validé pre/post automatiquement
require 'config/config.inc.php';

$context = Context::getContext();
$context->language = new Language(1);
$context->shop = new Shop(1);
$customer = new Customer(1);
$context->customer = $customer;

// 1. Create a cart rule with total quantity = 0 (Highlighted)
$cr0 = new CartRule();
$cr0->name = [1 => 'Rule Zero Qty'];
$cr0->code = 'ZERO_QTY';
$cr0->quantity = 0;
$cr0->quantity_per_user = 10;
$cr0->highlight = 1;
$cr0->active = 1;
$cr0->date_from = '2020-01-01 00:00:00';
$cr0->date_to = '2030-01-01 00:00:00';
$cr0->reduction_amount = 10;
$cr0->id_customer = 0;
$cr0->add();

// 2. Create a cart rule with quantity per user = 0 (Highlighted)
$cr1 = new CartRule();
$cr1->name = [1 => 'Rule Zero User'];
$cr1->code = 'ZERO_USER';
$cr1->quantity = 10;
$cr1->quantity_per_user = 0;
$cr1->highlight = 1;
$cr1->active = 1;
$cr1->date_from = '2020-01-01 00:00:00';
$cr1->date_to = '2030-01-01 00:00:00';
$cr1->reduction_amount = 10;
$cr1->id_customer = 0;
$cr1->add();

// 3. Create a valid cart rule (Highlighted)
$cr2 = new CartRule();
$cr2->name = [1 => 'Valid Rule'];
$cr2->code = 'VALID_RULE';
$cr2->quantity = 10;
$cr2->quantity_per_user = 10;
$cr2->highlight = 1;
$cr2->active = 1;
$cr2->date_from = '2020-01-01 00:00:00';
$cr2->date_to = '2030-01-01 00:00:00';
$cr2->reduction_amount = 10;
$cr2->id_customer = 0;
$cr2->add();

$controller = new DiscountController();
$controller->context = $context;
$rules = $controller->getTemplateVarCartRules();

$count = count($rules);
echo "Nombre de bons de réduction affichés : $count\n";

// Before fix: $count = 3 (highlighted rules are shown regardless of quantity)
// After fix: $count = 1 (only VALID_RULE is shown)
exit($count === 1 ? 0 : 1);
