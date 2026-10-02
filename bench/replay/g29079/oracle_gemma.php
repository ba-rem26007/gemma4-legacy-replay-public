<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29079, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Ticket: Bad default value for "Product::pack_stock_type"
 * 
 * The bug is a circular dependency:
 * Product::$pack_stock_type is initialized with Pack::STOCK_TYPE_DEFAULT.
 * Since Pack extends Product, loading Product triggers the loading of Pack, 
 * which in turn requires Product.
 * 
 * In some environments, this causes a crash (ClassNotFoundException).
 * In others, it's silently handled but the dependency remains.
 * 
 * To detect this without relying on a crash (which is environment-dependent),
 * we check if loading the Product class triggers the loading of the Pack class.
 * 
 * - Before fix: Loading Product -> triggers Pack (to resolve the constant).
 * - After fix: Loading Product -> triggers PackStockType (no dependency on Pack).
 */

try {
    // 1. Check if Pack is already loaded by the system (e.g., by config.inc.php)
    // The second parameter 'false' prevents the autoloader from being triggered.
    $packLoadedBefore = class_exists('Pack', false);
    $productLoadedBefore = class_exists('Product', false);

    if ($packLoadedBefore || $productLoadedBefore) {
        echo "Warning: Product or Pack classes are already loaded. Dependency check may be unreliable.\n";
        echo "Attempting to instantiate Pack to check for crash...\n";
        new Pack();
        echo "No crash occurred during Pack instantiation.\n";
        exit(0);
    }

    echo "Checking dependency: Loading Product...\n";
    
    // 2. Trigger the loading of the Product class.
    // This will execute the class definition and resolve default property values.
    new Product();

    // 3. Check if the Pack class was loaded as a side effect.
    $packLoadedAfter = class_exists('Pack', false);

    if ($packLoadedAfter) {
        echo "BUG DETECTED: Loading the Product class triggered the loading of the Pack class.\n";
        echo "This confirms the circular dependency (Product -> Pack).\n";
        exit(1);
    } else {
        echo "SUCCESS: Loading the Product class did NOT trigger the loading of the Pack class.\n";
        echo "The dependency on Pack::STOCK_TYPE_DEFAULT has been removed.\n";
        exit(0);
    }

} catch (\Throwable $t) {
    // If a crash actually occurs (the ClassNotFoundException mentioned in the ticket),
    // it means the bug is present.
    echo "CRASH DETECTED: " . $t->getMessage() . "\n";
    exit(1);
}
