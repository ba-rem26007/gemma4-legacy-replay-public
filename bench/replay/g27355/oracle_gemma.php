<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #27355, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);

/**
 * We create a concrete class extending Module.
 * Since Module implements ModuleInterface in recent versions, 
 * we must implement the abstract methods to avoid a Fatal Error.
 */
class TestModule extends Module {
    public $ps_versions_compliancy = [
        'min' => '8.0.0',
        'max' => '8.0.0'
    ];

    public function __construct($name = null, Context $context = null) {
        parent::__construct($name, $context);
    }

    // Implementation of ModuleInterface methods to allow instantiation
    public static function getInstance() { return null; }
    public static function hasValidInstance() { return false; }
    public function onInstall() { return true; }
    public function onUninstall() { return true; }
    public function onUpgrade() { return true; }
    public function onEnable() { return true; }
    public function onDisable() { return true; }
    public function onReset() { return true; }
    public function onUpdate() { return true; }
    public function onValidate() { return true; }
    public function onSave() { return true; }
}

try {
    // Instantiate the module to trigger the constructor padding logic
    $module = new TestModule();
    
    $observedMin = $module->ps_versions_compliancy['min'];
    $observedMax = $module->ps_versions_compliancy['max'];

    echo "Observed min_compliancy: $observedMin\n";
    echo "Observed max_compliancy: $observedMax\n";

    // The fix ensures that versions starting with 8 (or higher) are NOT padded.
    // Before fix: '8.0.0' (len 5) -> '8.0.0.0'
    // After fix: '8.0.0' remains '8.0.0'
    if ($observedMin === '8.0.0' && $observedMax === '8.0.0') {
        exit(0);
    } else {
        echo "FAIL: Version 8.0.0 was incorrectly padded to $observedMin / $observedMax\n";
        exit(1);
    }
} catch (\Throwable $t) {
    echo "Exception caught: " . $t->getMessage() . "\n";
    exit(1);
}
