<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33164, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Entity\AuthorizedApplication;

try {
    $reflectionClass = new ReflectionClass(AuthorizedApplication::class);
    $property = $reflectionClass->getProperty('name');
    $docComment = $property->getDocComment();

    echo "Analyzing AuthorizedApplication::name metadata...\n";
    echo "Doc comment found: " . trim($docComment) . "\n";

    // The bug is that the length was 255, which caused a Doctrine schema mismatch during upgrade.
    // The fix changes the length to 50.
    if (strpos($docComment, 'length=50') !== false && strpos($docComment, 'length=255') === false) {
        echo "SUCCESS: Column length is correctly set to 50.\n";
        exit(0);
    } else {
        echo "FAILURE: Column length is not 50 or still contains 255.\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
