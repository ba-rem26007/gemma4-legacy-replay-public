<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33700, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

// We use a temporary path to avoid overwriting the actual .htaccess of the environment
$testHtaccessPath = '/tmp/test_prestashop_htaccess';

// Ensure the file doesn't exist before the test
if (file_exists($testHtaccessPath)) {
    unlink($testHtaccessPath);
}

try {
    // Call the method that generates the .htaccess file
    // We pass the custom path as the first argument
    Tools::generateHtaccess($testHtaccessPath);

    if (!file_exists($testHtaccessPath)) {
        echo "Error: .htaccess file was not generated.\n";
        exit(1);
    }

    $content = file_get_contents($testHtaccessPath);
    echo "Generated .htaccess content:\n";
    echo "----------------------------\n";
    echo $content . "\n";
    echo "----------------------------\n";

    // Check if the "Options -Indexes" directive is present
    if (strpos($content, 'Options -Indexes') !== false) {
        echo "Success: 'Options -Indexes' found in .htaccess\n";
        unlink($testHtaccessPath);
        exit(0);
    } else {
        echo "Failure: 'Options -Indexes' NOT found in .htaccess\n";
        unlink($testHtaccessPath);
        exit(1);
    }
} catch (\Throwable $t) {
    echo "Exception caught: " . $t->getMessage() . "\n";
    if (file_exists($testHtaccessPath)) {
        unlink($testHtaccessPath);
    }
    exit(1);
}
