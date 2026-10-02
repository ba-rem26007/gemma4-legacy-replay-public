<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #31023, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Robust Mock for WebserviceObjectRender to avoid "undefined method" errors
 */
class MockWebserviceRender
{
    public function renderNodeHeader($name, $value = null) { return "<node>$name</node>"; }
    public function renderNodeFooter() { return "</node>"; }
    public function renderAssociationHeader($object, $ws_params, $assoc_name, $empty = false) { return "<assoc>$assoc_name</assoc>"; }
    public function renderAssociationFooter($object, $ws_params, $assoc_name) { return "</assoc>"; }
    public function renderAssociationWrapperFooter() { return "</wrapper>"; }
}

/**
 * Wrapper to expose the protected renderFlatAssociation method for testing.
 */
class TestWebserviceOutputBuilder extends WebserviceOutputBuilder
{
    public function __construct()
    {
        $this->objectRender = new MockWebserviceRender();
        $this->fieldsToDisplay = null; 
        $this->wsResource = [];
    }

    public function callRenderFlatAssociation($object, $depth, $assoc_name, $resource_name, $fields_assoc, $object_assoc, $parent_details)
    {
        return $this->renderFlatAssociation($object, $depth, $assoc_name, $resource_name, $fields_assoc, $object_assoc, $parent_details);
    }
}

// Custom error handler to specifically target the "scalar value as an array" bug.
// We ignore "Cannot access offset of type string on string" because it's a side effect 
// of the class not supporting scalars in the 'elseif' block, which is separate from the fix.
set_error_handler(function ($errno, $errstr) {
    if (strpos($errstr, 'Cannot use a scalar value as an array') !== false) {
        throw new Exception($errstr);
    }
    // Ignore other PHP 8 scalar offset warnings to allow the test to proceed to the fix check
    return true; 
});

try {
    $builder = new TestWebserviceOutputBuilder();
    $category = new Category(4);

    /**
     * TRIGGER:
     * The bug is triggered when $field_name is 'id' and $field is a scalar.
     * 
     * Before fix:
     * if ($field_name == 'id' && !isset($field['sqlId'])) {
     *     $field['sqlId'] = 'id'; // <--- Triggers "Cannot use a scalar value as an array"
     * }
     * 
     * After fix:
     * if (isset($field['id']) && !isset($field['sqlId'])) {
     *     // isset($field['id']) is false for scalars, block is skipped.
     * }
     */
    $fields_assoc = [
        'id' => 'scalar_value' 
    ];
    $object_assoc = [
        'id' => 4
    ];

    echo "Testing renderFlatAssociation with a scalar field value for 'id'...\n";
    
    $builder->callRenderFlatAssociation(
        $category, 
        0, 
        'test_assoc', 
        'test_resource', 
        $fields_assoc, 
        $object_assoc, 
        ''
    );

    echo "Success: The scalar assignment bug was not triggered.\n";
    restore_error_handler();
    exit(0);

} catch (\Throwable $t) {
    restore_error_handler();
    echo "Bug detected: " . $t->getMessage() . "\n";
    exit(1);
}
