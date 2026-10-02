<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #30996, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Core\Grid\Query\ProductQueryBuilder;

// Setup context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

// Enable Multishop
Configuration::updateValue('PS_MULTISHOP_ACTIVE', 1);

try {
    // ProductQueryBuilder is a Symfony-style class. 
    // We instantiate it via Reflection to bypass the constructor dependencies.
    $reflection = new ReflectionClass(ProductQueryBuilder::class);
    $builder = $reflection->newInstanceWithoutConstructor();
    
    $propPrefix = $reflection->getProperty('dbPrefix');
    $propPrefix->setAccessible(true);
    $propPrefix->setValue($builder, _DB_PREFIX_);

    // addShopCondition is protected, we access it via Reflection
    $method = $reflection->getMethod('addShopCondition');
    $method->setAccessible(true);

    // Trigger the bug: filteredShopGroupId is set, shopId is null
    $sqlBase = 'ps.`id_product` = p.`id_product`';
    $tableAlias = 'ps';
    $shopId = null;
    $filteredShopGroupId = 1;

    $condition = $method->invoke($builder, $sqlBase, $tableAlias, $shopId, $filteredShopGroupId);
    
    echo "Generated condition: $condition\n";

    // Normalize whitespace to avoid failures due to newlines or indentation in the diff
    $normalized = preg_replace('/\s+/', ' ', $condition);

    /**
     * BUGGY: The condition ps2.id_product = ps.id_product is in the ON clause.
     * FIXED: The condition ps2.id_product = ps.id_product is in the WHERE clause.
     */
    $buggyPattern = 'ON ps2.id_shop = s2.id_shop AND ps2.id_product = ps.id_product WHERE';
    $fixedPattern = 'ON ps2.id_shop = s2.id_shop WHERE s2.id_shop_group = :filteredShopGroupId AND ps2.id_product = ps.id_product';

    if (strpos($normalized, $buggyPattern) !== false) {
        echo "Bug detected: Correlated condition found in the ON clause of the subquery.\n";
        exit(1);
    }

    if (strpos($normalized, $fixedPattern) !== false) {
        echo "Fix verified: Correlated condition moved to the WHERE clause.\n";
        exit(0);
    }

    echo "Neither buggy nor fixed pattern found. Normalized condition: $normalized\n";
    exit(1);

} catch (\Throwable $e) {
    echo "Exception caught: " . $e->getMessage() . "\n";
    exit(1);
}
