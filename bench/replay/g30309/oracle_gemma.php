<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #30309, validé pre/post automatiquement
require 'config/config.inc.php';

// Fix for CLI environment to avoid warnings in WebserviceOutputBuilder
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['REQUEST_METHOD'] = 'GET';

/**
 * The bug occurs in WebserviceOutputBuilder::renderFlatAssociation.
 * When $fields_assoc is a numerically indexed array (e.g. [0 => ['id' => 123]]),
 * the old code checks if the key ($field_name) is equal to 'id'.
 * Since 0 != 'id', it falls into the elseif and sets $field['sqlId'] = $field_name (which is 0).
 * This results in an XML tag <0>, which is invalid.
 * 
 * The fix changes the check to see if the field array itself contains an 'id' key.
 */

// Mock renderer to avoid "Class not found" and interface dependency
class MockWebserviceRenderer {
    public function renderField($object, $params, $name, $field, $depth) {
        // The builder passes the resolved field name as $name.
        // If the bug is present, $name will be 0.
        return "<$name>value</$name>";
    }
}

class TestWebserviceOutputBuilder extends WebserviceOutputBuilder
{
    // Bypass the WebserviceOutputInterface type hint by injecting the renderer directly
    public function setMockRender($render)
    {
        $this->objectRender = $render;
    }

    public function publicRenderFlatAssociation($object, $depth, $assoc_name, $resource_name, $fields_assoc, $object_assoc, $parent_details)
    {
        return $this->renderFlatAssociation($object, $depth, $assoc_name, $resource_name, $fields_assoc, $object_assoc, $parent_details);
    }
}

try {
    // Setup Context
    Context::getContext()->shop = new Shop(1);
    Context::getContext()->language = new Language(1);

    // Instantiate the builder
    $builder = new TestWebserviceOutputBuilder('/api');
    $builder->setMockRender(new MockWebserviceRenderer());

    // Mock data that triggers the bug:
    // $fields_assoc is a numerically indexed array where the element contains an 'id' key.
    // This happens in renderAssociations when $fields_assoc is empty and the association value has an 'id'.
    $fields_assoc = [
        0 => ['id' => 123] 
    ];
    $object_assoc = ['id' => 123];
    
    // Use an existing product from demo data
    $product = new Product(1);

    // Execute the method
    $output = $builder->publicRenderFlatAssociation(
        $product, 
        0, 
        'test_assoc', 
        'test_resource', 
        $fields_assoc, 
        $object_assoc, 
        ''
    );

    echo "Output generated: " . $output . "\n";

    // The bug is the presence of a tag starting with a number (specifically <0>)
    if (strpos($output, '<0>') !== false) {
        echo "Bug reproduced: XML tag starts with a number (<0>).\n";
        exit(1);
    }

    // The fix ensures the tag is <id>
    if (strpos($output, '<id>') !== false) {
        echo "Success: XML tag is correctly named <id>.\n";
        exit(0);
    }

    echo "Unexpected output format. Neither <0> nor <id> found.\n";
    exit(1);

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
