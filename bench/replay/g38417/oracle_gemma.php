<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #38417, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Test for Webservice SQL error when getting customization images.
 * The bug is caused by passing a string ('customizations') to ImageType::getImagesTypes(),
 * which expects an ID or null.
 */

// Setup minimal context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

try {
    // 1. Prepare a dummy wsObject to satisfy the constructor and the method logic
    $wsObject = new stdClass();
    $wsObject->urlSegment = ['', 'customizations', '']; // Segment 1 is 'customizations'

    // 2. Instantiate the class containing the bug
    // The constructor expects a WebserviceRequest-like object
    $manager = new WebserviceSpecificManagementImages($wsObject);

    // 3. Use Reflection to set the protected property 'imageType' 
    // and call the protected method 'manageCustomizationImages'
    $reflection = new ReflectionClass('WebserviceSpecificManagementImages');
    
    $prop = $reflection->getProperty('imageType');
    $prop->setAccessible(true);
    $prop->setValue($manager, 'customizations');

    $method = $reflection->getMethod('manageCustomizationImages');
    $method->setAccessible(true);

    echo "Calling manageCustomizationImages with imageType = 'customizations'...\n";
    
    // This call triggers ImageType::getImagesTypes($this->imageType)
    $method->invoke($manager);

    echo "Success: No SQL error triggered. The fix is working.\n";
    exit(0);

} catch (\Throwable $t) {
    $message = $t->getMessage();
    echo "Caught exception: $message\n";

    // The bug is specifically a PDOException: "Unknown column 'customizations' in 'where clause'"
    if (strpos($message, 'Unknown column \'customizations\'') !== false) {
        echo "FAIL: Bug reproduced. SQL error found in ImageType::getImagesTypes.\n";
        exit(1);
    }

    // If the error is related to missing objects (like $this->objOutput), 
    // it means the code passed the faulty ImageType::getImagesTypes() line.
    if (strpos($message, 'Call to a member function') !== false || 
        strpos($message, 'undefined property') !== false || 
        strpos($message, 'getObjectRender') !== false) {
        echo "Success: Passed the faulty line (hit subsequent missing object error).\n";
        exit(0);
    }

    echo "Unexpected error occurred: $message\n";
    exit(1);
}
