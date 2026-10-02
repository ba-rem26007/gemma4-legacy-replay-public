<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #31032, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Test for Ticket: Wrong contextual mail logo in multistore
 * 
 * The bug: Mail::send used Configuration::get('PS_LOGO_MAIL') without the $idShop parameter,
 * relying on the current context shop instead of the shop associated with the email.
 * 
 * Strategy:
 * 1. Set Shop 1 logo to a non-existent file (file_exists = false).
 * 2. Set Shop 2 logo to a directory (file_exists = true, but attaching it will cause a failure).
 * 3. Set context to Shop 1.
 * 4. Call Mail::send for Shop 2.
 * 
 * - Before fix: Mail::send uses Shop 1 logo -> file_exists is false -> logo is ignored -> Mail::send succeeds.
 * - After fix: Mail::send uses Shop 2 logo -> file_exists is true -> tries to attach a directory -> Mail::send fails.
 */

// 1. Setup Configuration
$idShop1 = 1;
$idShop2 = 2;

// Shop 1: Logo does not exist
Configuration::updateValue('PS_LOGO_MAIL', 'non_existent_logo_123.jpg', false, null, $idShop1);
// Shop 2: Logo is a directory (img/p always exists in PrestaShop)
Configuration::updateValue('PS_LOGO_MAIL', 'p', false, null, $idShop2);

// Ensure mail is not disabled
Configuration::updateValue('PS_MAIL_METHOD', 1); // Use PHP mail

// 2. Set context to Shop 1
Context::getContext()->shop = new Shop($idShop1);

echo "Context Shop ID: $idShop1\n";
echo "Target Shop ID: $idShop2\n";

// 3. Execute Mail::send for Shop 2
// Signature: send($idLang, $template, $subject, $templateVars, $to, $toName, $from, $fromName, $fileAttachment, $mode_smtp, $template_path, $idShop)
try {
    $result = Mail::send(
        1, 
        'contact', 
        'Test Subject', 
        [], 
        'test@example.com', 
        null, 
        'from@example.com', 
        'From Name', 
        null, 
        null, 
        null, 
        $idShop2
    );
    echo "Mail::send result: " . var_export($result, true) . "\n";
} catch (\Throwable $e) {
    echo "Mail::send threw exception: " . $e->getMessage() . "\n";
    $result = false;
}

/**
 * Validation:
 * If the fix is applied, Mail::send will attempt to attach the directory 'img/p', 
 * which will cause the mailer to fail (return false or throw exception).
 * If the bug is present, it will use the logo of Shop 1 (non-existent), 
 * skip the attachment, and the mail will be "sent" successfully.
 */

if ($result === false || $result === 0) {
    echo "Success: The mail failed because it tried to use the Shop 2 logo (Fixed).\n";
    exit(0);
} else {
    echo "Failure: The mail succeeded because it ignored the Shop 2 logo and used the context (Buggy).\n";
    exit(1);
}
