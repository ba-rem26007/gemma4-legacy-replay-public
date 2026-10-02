<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29340, validé pre/post automatiquement
require 'config/config.inc.php';

try {
    // Setup Context
    $context = Context::getContext();
    $context->shop = new Shop(1);
    $context->language = new Language(1);
    $context->employee = new Employee(1);
    $context->device = 1; // Trigger the device-specific join logic

    // Use unique names to avoid collisions
    $mName = 'testmodule_' . uniqid();
    $hName = 'testHook_' . uniqid();

    // Setup Demo Data via DB (Module is abstract, cannot be instantiated)
    Db::getInstance()->insert('module', [
        'name' => $mName,
        'active' => 1,
        'version' => '1.0'
    ]);
    $moduleId = (int)Db::getInstance()->Insert_id();

    Db::getInstance()->insert('hook', [
        'name' => $hName,
        'title' => 'Test Hook',
        'active' => 1,
        'position' => 1
    ]);
    $hookId = (int)Db::getInstance()->Insert_id();

    Db::getInstance()->insert('module_shop', [
        'id_module' => $moduleId,
        'id_shop' => 1,
        'enable_device' => 7
    ]);

    Db::getInstance()->insert('hook_module', [
        'id_module' => $moduleId,
        'id_hook' => $hookId,
        'id_shop' => 1,
        'position' => 1
    ]);

    // Attempt to enable MySQL General Log to capture the query
    // We wrap this in a try-catch because the DB user might lack SUPER privileges
    try {
        Db::getInstance()->execute('SET GLOBAL general_log = "OFF"');
        Db::getInstance()->execute('SET GLOBAL log_output = "TABLE"');
        Db::getInstance()->execute('SET GLOBAL general_log = "ON"');
    } catch (\Throwable $e) {
        // Log failure is acceptable, we will try Reflection as fallback
    }

    // Trigger the code that generates the query
    Hook::getHookModuleExecList($hName);

    $sql = null;

    // Method 1: Try to retrieve from mysql.general_log using executeS to avoid getValue issues
    try {
        $results = Db::getInstance()->executeS("SELECT `argument` FROM `mysql`.`general_log` WHERE `argument` LIKE '%" . pSQL($hName) . "%' ORDER BY `event_time` DESC LIMIT 1");
        if (!empty($results)) {
            $sql = $results[0]['argument'];
        }
    } catch (\Throwable $e) {
        // Fallback to Method 2
    }

    // Method 2: Fallback to Reflection to check if _queries is populated (if profiling was enabled)
    if (!$sql) {
        try {
            $db = Db::getInstance();
            $reflector = new ReflectionClass($db);
            if ($reflector->hasProperty('_queries')) {
                $prop = $reflector->getProperty('_queries');
                $prop->setAccessible(true);
                $queries = $prop->getValue($db);
                if (is_array($queries)) {
                    foreach (array_reverse($queries) as $q) {
                        if (strpos($q, $hName) !== false) {
                            $sql = $q;
                            break;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Both methods failed
        }
    }

    if (!$sql) {
        echo "Could not capture the executed SQL query. Ensure DB user has SUPER privileges or profiling is enabled.\n";
        exit(1);
    }

    echo "Executed SQL: $sql\n";

    // The bug is that ps_module_shop is joined twice.
    // Before fix: 1 join for device check + 1 redundant join (alias mshop/ms).
    // After fix: Only 1 join.
    $occurrences = substr_count($sql, 'ps_module_shop');
    echo "Occurrences of ps_module_shop: $occurrences\n";

    // Cleanup
    try {
        Db::getInstance()->execute('SET GLOBAL general_log = "OFF"');
    } catch (\Throwable $e) {}

    // If occurrences > 1, the bug is still present.
    exit($occurrences <= 1 ? 0 : 1);

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
