<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33255, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);
$employee = new Employee(1);
Context::getContext()->employee = $employee;

// Enable Multistore
Configuration::updateValue('PS_MULTISHOP_FEATURE_ACTIVE', 1);

// Setup data: Shop and Shop URLs
$db = Db::getInstance();
$db->execute('DELETE FROM ' . _DB_PREFIX_ . 'shop_url');
$db->execute('INSERT INTO ' . _DB_PREFIX_ . 'shop (id_shop, name, active) VALUES (1, "Shop 1", 1) ON DUPLICATE KEY UPDATE name="Shop 1"');

// URL 1: Not main
$db->execute('INSERT INTO ' . _DB_PREFIX_ . 'shop_url (id_shop_url, id_shop, domain, physical_uri, virtual_uri, main, active) 
              VALUES (1, 1, "domain1.com", "/", "", 0, 1)');
// URL 2: Main
$db->execute('INSERT INTO ' . _DB_PREFIX_ . 'shop_url (id_shop_url, id_shop, domain, physical_uri, virtual_uri, main, active) 
              VALUES (2, 1, "domain2.com", "/", "", 1, 1)');

// Mock GET parameters for the controller
$_GET['id_shop'] = 1;
$_GET['token'] = 'dummy_token';

try {
    $controller = new AdminShopUrlController();

    // 1. Verify the existence and logic of the new method getUnremovableUrls
    if (!method_exists($controller, 'getUnremovableUrls')) {
        echo "FAILURE: Method getUnremovableUrls does not exist. This is the old code.\n";
        exit(1);
    }

    $reflectionMethod = new ReflectionMethod('AdminShopUrlController', 'getUnremovableUrls');
    $reflectionMethod->setAccessible(true);
    $unremovableUrls = $reflectionMethod->invoke($controller);

    echo "Unremovable URLs (main=1): " . implode(', ', $unremovableUrls) . "\n";
    if (!in_array(2, $unremovableUrls)) {
        echo "FAILURE: Main URL (2) is not identified as unremovable.\n";
        exit(1);
    }

    // 2. Verify that renderList() applies this logic to the skip list
    try {
        $controller->renderList();
    } catch (\Throwable $t) {
        // Ignore template loading errors in CLI
    }

    // Instead of guessing the property name (which varies by PS version), 
    // we scan all properties of the controller to find the one containing the skip list.
    $found = false;
    $reflectionClass = new ReflectionClass($controller);
    foreach ($reflectionClass->getProperties() as $prop) {
        $prop->setAccessible(true);
        $val = $prop->getValue($controller);
        
        // We are looking for an array that has a 'delete' key containing our main URL ID (2)
        if (is_array($val) && isset($val['delete']) && is_array($val['delete'])) {
            if (in_array(2, $val['delete'])) {
                echo "SUCCESS: Found skip list in property '{$prop->getName()}' containing URL 2.\n";
                $found = true;
                break;
            }
        }
    }

    if ($found) {
        exit(0);
    } else {
        echo "FAILURE: Main URL (2) was not added to any action skip list in renderList().\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "ERROR: " . $t->getMessage() . "\n";
    exit(1);
}
