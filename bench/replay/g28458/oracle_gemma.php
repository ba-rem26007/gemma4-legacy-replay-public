<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #28458, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);

/**
 * Mock Template class to avoid "Unable to load template" error in CLI
 * and to inspect the data assigned to the template.
 */
class MockTemplate {
    public static $lastAssignedData = [];
    public function assign($data) {
        self::$lastAssignedData = array_merge(self::$lastAssignedData, $data);
    }
    public function fetch() {
        return 'mock_rendered_content';
    }
}

/**
 * Extend HelperList to override createTemplate and return our mock.
 * createTemplate is public in HelperCore.
 */
class TestHelperList extends HelperList {
    public function createTemplate($tpl) {
        return new MockTemplate();
    }
}

// Instantiate the test class
$hl = new TestHelperList();

// Configure HelperList to avoid crashes and target the specific logic
$hl->currentIndex = 'index.php'; 
$hl->identifier = 'id_product';
$hl->token = 'token123';
$hl->table = 'product';

// TRIGGER: Set specificConfirmDelete to false.
// Before the fix, the 'confirm' key was only added if specificConfirmDelete !== false.
// If it was false, the key was missing, causing a notice in the template.
$hl->specificConfirmDelete = false;

try {
    // We call the method. The mock prevents the .tpl file from being loaded.
    $hl->displayDeleteLink('token123', 1, 'Test Product');
    
    $assigned = MockTemplate::$lastAssignedData;

    if (!isset($assigned['confirm'])) {
        echo "Bug detected: 'confirm' key is missing in template assignment when specificConfirmDelete is false.\n";
        exit(1);
    }

    echo "Success: 'confirm' key is present in template assignment.\n";
    exit(0);

} catch (\Throwable $t) {
    echo "Unexpected exception: " . $t->getMessage() . "\n";
    exit(1);
}
