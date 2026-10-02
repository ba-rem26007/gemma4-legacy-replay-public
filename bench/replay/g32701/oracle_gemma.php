<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #32701, validé pre/post automatiquement
require 'config/config.inc.php';

try {
    // 1. Setup: Create an attachment to delete
    // Attachment is a multi-language object, 'name' must be an array indexed by id_lang
    $attachment = new Attachment();
    $attachment->mime = 'application/pdf';
    $attachment->name = [1 => 'test_document.pdf']; 
    $attachment->file_name = 'test_document.pdf';
    $attachment->file = 'test_document.pdf';
    $attachment->file_size = 1024;
    
    if (!$attachment->add()) {
        echo "Failed to create attachment for test\n";
        exit(1);
    }
    $id_attachment = (int)$attachment->id;
    echo "Created attachment with ID: $id_attachment\n";

    // 2. Mock the Webservice Request
    // We must set the method BEFORE instantiating WebserviceRequest 
    // because the constructor typically reads $_SERVER['REQUEST_METHOD']
    $_SERVER['REQUEST_METHOD'] = 'DELETE';
    $request = new WebserviceRequest();
    
    // Ensure the method is explicitly set in case the constructor behaves differently in CLI
    $request->method = 'DELETE';
    
    // The URL is /api/attachments/file/1
    // urlSegment[0] = 'attachments'
    // urlSegment[1] = 'file'
    // urlSegment[2] = '1'
    $request->urlSegment = ['attachments', 'file', (string)$id_attachment];
    
    // 3. Instantiate the specific management class and execute
    $manager = new WebserviceSpecificManagementAttachments();
    $manager->setWsObject($request);
    
    echo "Calling manage() with DELETE method and urlSegment[2] = $id_attachment\n";
    $manager->manage();

    // 4. Verification
    // We check if the object still exists in the database
    $check = new Attachment($id_attachment);
    if (Validate::isLoadedObject($check)) {
        echo "FAILURE: Attachment $id_attachment still exists in the database.\n";
        exit(1);
    } else {
        echo "SUCCESS: Attachment $id_attachment has been deleted.\n";
        exit(0);
    }

} catch (\Throwable $t) {
    echo "An error occurred: " . $t->getMessage() . "\n";
    exit(1);
}
