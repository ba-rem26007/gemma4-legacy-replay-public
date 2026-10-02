<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #28358, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Controller\Admin\Configure\AdvancedParameters\WebserviceController;

/**
 * The bug is that checkWebserviceEndpoint does not catch Guzzle exceptions.
 * The fix adds a try-catch block and calls $this->addFlash().
 * In CLI, addFlash() fails because the Symfony container/session is not initialized.
 * We use an anonymous class to override addFlash() and avoid the "Call to a member function has() on null" error.
 */

$controller = new class extends WebserviceController {
    public function addFlash($type, $message) 
    {
        echo "Flash message captured: [$type] $message\n";
    }
};

// Since checkWebserviceEndpoint is private, we use Reflection to test it
$reflection = new ReflectionClass(WebserviceController::class);
$method = $reflection->getMethod('checkWebserviceEndpoint');
$method->setAccessible(true);

// Use a URL that is guaranteed to cause a connection failure (ConnectException)
$url = 'http://invalid-domain-prestashop-test.local';

echo "Testing checkWebserviceEndpoint with URL: $url\n";

try {
    // Before fix: This throws a GuzzleHttp\Exception\ConnectException
    // After fix: This catches the exception, calls addFlash(), and returns false
    $result = $method->invoke($controller, $url);
    
    echo "Result: " . ($result === false ? 'false' : 'true') . "\n";
    
    if ($result === false) {
        echo "Success: Exception was caught and method returned false.\n";
        exit(0);
    } else {
        echo "Failure: Method returned true for an invalid URL.\n";
        exit(1);
    }
} catch (\Throwable $t) {
    echo "Caught unexpected exception: " . get_class($t) . "\n";
    echo "Message: " . $t->getMessage() . "\n";
    exit(1);
}
