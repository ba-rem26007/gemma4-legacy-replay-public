<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #31279, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

$complexEmail = 'part1-part2-part3part1-part2-part3part1-part2-part3@subdomain.domain.com';

try {
    echo "Testing email: $complexEmail\n";
    
    // The bug is located in Validate::isEmail()
    // Before the fix, NoRFCWarningsValidation made this return false for complex but valid RFC emails.
    $isValid = Validate::isEmail($complexEmail);
    
    echo "Result of Validate::isEmail: " . ($isValid ? 'VALID' : 'INVALID') . "\n";
    
    if ($isValid) {
        exit(0); // Corrected
    } else {
        exit(1); // Still failing
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
