<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29638, validé pre/post automatiquement
require 'config/config.inc.php';

try {
    // 1. Setup: Create an existing attachment
    // We must ensure all required fields are set to avoid "property is empty" exceptions
    $attachment = new Attachment();
    $attachment->file = 'old_file.txt';
    $attachment->file_name = 'old_file.txt';
    $attachment->mime = 'text/plain';
    $attachment->file_size = 100;
    
    $defaultLangId = (int)Configuration::get('PS_LANG_DEFAULT');
    $attachment->name = [];
    $attachment->name[$defaultLangId] = 'Original Name';
    
    if (!$attachment->add()) {
        echo "FAIL: Could not create attachment\n";
        exit(1);
    }
    $id = $attachment->id;
    echo "Attachment created with ID: $id, Name: {$attachment->name[$defaultLangId]}\n";

    // 2. Simulate a PATCH request to update the file
    // To avoid the Fatal Error regarding the signature of setWsObject(), 
    // we instantiate the Core class and use Reflection to inject the dependency.
    $request = new WebserviceRequest();
    $request->method = 'PATCH';
    $request->urlSegment = ['file', $id];

    // We use the Core class directly
    $manager = new WebserviceSpecificManagementAttachmentsCore();
    
    $reflector = new ReflectionClass($manager);
    $property = $reflector->getProperty('wsObject');
    $property->setAccessible(true);
    $property->setValue($manager, $request);

    // Simulate the environment for executeFileAddAndEdit()
    $_SERVER['REQUEST_METHOD'] = 'PATCH';
    $_POST['name'] = 'New Name (should not overwrite)';
    
    $tmpFile = tempnam(sys_get_temp_dir(), 'ps_test');
    file_put_contents($tmpFile, 'new content');
    
    $_FILES['file'] = [
        'name' => 'new_file.txt',
        'type' => 'text/plain',
        'tmp_name' => $tmpFile,
        'error' => 0,
        'size' => strlen('new content'),
    ];

    // Execute the logic
    // manage() calls manageAttachments() which now handles 'PATCH'
    $manager->manage();

    // 3. Verification
    $updatedAttachment = new Attachment($id);
    
    echo "Updated File: " . $updatedAttachment->file . "\n";
    echo "Updated Name: " . $updatedAttachment->name[$defaultLangId] . "\n";

    // The file should be updated (the internal filename changes)
    $fileUpdated = ($updatedAttachment->file !== 'old_file.txt');
    // The name should be preserved because it was already set (the fix: if ($attachment->name[...] === null))
    $namePreserved = ($updatedAttachment->name[$defaultLangId] === 'Original Name');

    if (!$fileUpdated) {
        echo "FAIL: The file was not updated. PATCH method is likely not handled in manageAttachments().\n";
        exit(1);
    }

    if (!$namePreserved) {
        echo "FAIL: The attachment name was overwritten, but it should have been preserved.\n";
        exit(1);
    }

    echo "SUCCESS: File updated and name preserved via PATCH.\n";
    exit(0);

} catch (\Throwable $t) {
    echo "EXCEPTION: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
    exit(1);
}
