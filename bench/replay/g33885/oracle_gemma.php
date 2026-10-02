<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33885, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Wrapper to access protected methods of FrontController
 */
class TestFrontController extends FrontController
{
    public function publicSanitizeUrl(string $url): string
    {
        return $this->sanitizeUrl($url);
    }
}

// 1. Setup Environment
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

// 2. Simulate the bug trigger: 
// The bug occurs when $_GET contains parameters that are already part of a rewritten URL.
// Before the fix, sanitizeUrl() iterates over $_GET and appends these parameters to the URL.
$_GET['slug'] = 'des-conseils';
$_GET['module'] = 'smartblog';
$_GET['controller'] = 'category';

// This is a rewritten URL (no query parameters)
$rewrittenUrl = 'http://localhost/fr/blog/category/15_des-conseils.html';

// 3. Execute the code
$fc = new TestFrontController();
$fc->context = $context;

try {
    echo "Input URL: $rewrittenUrl\n";
    echo "Current \$_GET: " . http_build_query($_GET) . "\n";
    
    $sanitizedUrl = $fc->publicSanitizeUrl($rewrittenUrl);
    
    echo "Sanitized URL: $sanitizedUrl\n";
    
    // The bug is that the parameters from $_GET are appended to the rewritten URL.
    // After the fix, sanitizeUrl should only process parameters already present in the URL string.
    $bugFound = false;
    if (strpos($sanitizedUrl, 'slug=des-conseils') !== false || strpos($sanitizedUrl, 'module=smartblog') !== false) {
        $bugFound = true;
    }

    if ($bugFound) {
        echo "BUG: Unwanted parameters from \$_GET were appended to the rewritten URL.\n";
        exit(1);
    } else {
        echo "SUCCESS: Rewritten URL remained clean.\n";
        exit(0);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
