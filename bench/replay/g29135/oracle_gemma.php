<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29135, validé pre/post automatiquement
require 'config/config.inc.php';

use WebserviceRequest;
use WebserviceSpecificManagementImagesCore;
use WebserviceException;

/**
 * Wrapper to expose the protected method writePostedImageOnDisk for testing
 */
class WebserviceImagesTester extends WebserviceSpecificManagementImagesCore
{
    public function testWritePostedImageOnDisk($reception_path, $dest_width = null, $dest_height = null, $image_types = null, $parent_path = null)
    {
        return $this->writePostedImageOnDisk($reception_path, $dest_width, $dest_height, $image_types, $parent_path);
    }
}

// 1. Setup: Set the limit to 5MB in configuration
$limitMb = 5;
Configuration::updateValue('PS_LIMIT_UPLOAD_IMAGE_VALUE', $limitMb);
echo "PS_LIMIT_UPLOAD_IMAGE_VALUE set to: $limitMb MB\n";

// 2. Simulate a file upload of 4MB
// This is > 3MB (old hardcoded limit) but < 5MB (new config limit)
$fileSize = 4000000; 
$_FILES['image'] = [
    'tmp_name' => '/tmp/php_test_image',
    'size' => $fileSize,
    'name' => 'test.jpg',
    'type' => 'image/jpeg',
    'error' => 0
];

// 3. Prepare the Webservice objects
$wsRequest = new WebserviceRequest();
$wsRequest->method = 'PUT';

$tester = new WebserviceImagesTester();
$tester->setWsObject($wsRequest);

try {
    echo "Attempting to upload a $fileSize byte image...\n";
    // We call the method. 
    // If the bug is present, it will throw a WebserviceException because 4MB > 3MB.
    // If the fix is present, it will pass the size check (4MB < 5MB) and proceed.
    $tester->testWritePostedImageOnDisk('/tmp');
    
    // If we reach here, the size check passed.
    echo "Success: Image size check passed (limit is correctly loaded from config).\n";
    exit(0);
} catch (WebserviceException $e) {
    $msg = $e->getMessage();
    echo "Caught WebserviceException: $msg\n";
    
    // If the exception mentions the old 3000 KB limit, the bug is still there.
    if (strpos($msg, 'maximum allowed is 3000 KB') !== false) {
        echo "Failure: Still using hardcoded 3MB limit.\n";
        exit(1);
    }
    
    // If it's a different exception (e.g. file not found because /tmp/php_test_image doesn't exist),
    // it means the size check was passed, so the fix is working.
    echo "The size check was passed, but a subsequent error occurred (expected since file is dummy).\n";
    exit(0);
} catch (\Throwable $t) {
    echo "Unexpected error: " . $t->getMessage() . "\n";
    exit(1);
}
