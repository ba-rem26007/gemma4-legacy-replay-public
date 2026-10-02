<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29741, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Ticket BOOM-5382: The configuration data is lost after reloading dashboard page.
 * 
 * The issue is that ConfigurationKPI inherits from Configuration and switches 
 * the table definition to 'configuration_kpi'. 
 * 
 * In PrestaShop, Configuration::loadConfiguration() is typically called during 
 * the boot process to populate the static cache from the default 'configuration' table.
 * 
 * If ConfigurationKPI::get() is called after the global loadConfiguration(), 
 * the system may fail to retrieve the KPI-specific value if the cache for the 
 * 'configuration_kpi' table hasn't been initialized, especially if the 
 * Configuration::get() logic assumes that a call to loadConfiguration() 
 * means the cache is already complete for the current table.
 * 
 * The fix ensures that when the KPI definition is set, the cache for the 
 * 'configuration_kpi' table is explicitly loaded if it's empty.
 */

$key = 'BOOM_5382_KPI_KEY';
$value = 'BOOM_5382_KPI_VALUE';

// 1. Setup: Ensure the value exists ONLY in the KPI table.
// This isolates the test from any potential collisions with the global configuration table.
ConfigurationKPI::updateValue($key, $value);
Configuration::deleteByName($key);

// 2. Simulate a page reload: Clear the static cache.
try {
    $ref = new ReflectionClass('Configuration');
    $cacheProp = $ref->getProperty('_cache');
    $cacheProp->setAccessible(true);
    $cacheProp->setValue(null, []);
} catch (\Throwable $e) {
    echo "Reflection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Simulate the PrestaShop boot process.
// Configuration::loadConfiguration() is called for the default 'ps_configuration' table.
// This populates the cache, but NOT for the 'ps_configuration_kpi' table.
Configuration::loadConfiguration();

// 4. Attempt to retrieve the KPI value.
// Before the fix: ConfigurationKPI::get() switches the table to 'configuration_kpi'.
// If the internal logic of Configuration::get() sees that loadConfiguration() 
// was already called, it might return the default value (false) instead of 
// querying the DB for the new table.
// After the fix: ConfigurationKPI::setKpiDefinition() detects the KPI cache is empty 
// and calls parent::loadConfiguration(), populating the cache with the KPI value.
$observedValue = ConfigurationKPI::get($key);

echo "Expected value: $value\n";
echo "Observed value: " . var_export($observedValue, true) . "\n";

if ($observedValue === $value) {
    echo "SUCCESS: The KPI value was correctly retrieved.\n";
    exit(0);
} else {
    echo "BUG DETECTED: The KPI value was lost (returned default/false).\n";
    exit(1);
}
