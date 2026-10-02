<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #38100, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\EventListener\Admin\EmployeeSessionSubscriber;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Test for Ticket: Cookie mechanism is broken after refactoring for 9.0.0
 * The fix involves:
 * 1. Adding 'KernelEvents::RESPONSE' to getSubscribedEvents.
 * 2. Implementing the 'onKernelResponse' method.
 * 3. Calling $this->legacyContext->getContext()->cookie->write() inside that method.
 */

echo "Testing Cookie persistence fix in EmployeeSessionSubscriber...\n";

// 1. Verify the event is registered in getSubscribedEvents
$events = EmployeeSessionSubscriber::getSubscribedEvents();
$hasResponseEvent = isset($events[KernelEvents::RESPONSE]) && $events[KernelEvents::RESPONSE] === 'onKernelResponse';

echo "Event KernelEvents::RESPONSE registered: " . ($hasResponseEvent ? 'YES' : 'NO') . "\n";

// 2. Verify the method onKernelResponse exists
$methodExists = method_exists(EmployeeSessionSubscriber::class, 'onKernelResponse');
echo "Method onKernelResponse exists: " . ($methodExists ? 'YES' : 'NO') . "\n";

// 3. Verify the method contains the logic to write the cookie
$logicCorrect = false;
if ($methodExists) {
    try {
        $refMethod = new ReflectionMethod(EmployeeSessionSubscriber::class, 'onKernelResponse');
        $fileName = $refMethod->getFileName();
        $startLine = $refMethod->getStartLine();
        $endLine = $refMethod->getEndLine();
        
        $lines = file($fileName);
        $methodBody = "";
        for ($i = $startLine - 1; $i < $endLine; $i++) {
            $methodBody .= $lines[$i];
        }

        if (strpos($methodBody, 'cookie->write()') !== false) {
            $logicCorrect = true;
        }
    } catch (\Throwable $t) {
        echo "Error analyzing method body: " . $t->getMessage() . "\n";
    }
}
echo "Method contains cookie->write() call: " . ($logicCorrect ? 'YES' : 'NO') . "\n";

if ($hasResponseEvent && $methodExists && $logicCorrect) {
    echo "RESULT: Fix is present and logic is correct.\n";
    exit(0);
} else {
    echo "RESULT: Fix is missing or incomplete.\n";
    exit(1);
}
