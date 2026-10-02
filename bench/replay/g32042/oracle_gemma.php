<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #32042, validé pre/post automatiquement
require 'config/config.inc.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use PrestaShopBundle\Controller\Admin\Configure\AdvancedParameters\WebserviceController;

/**
 * Mock classes to simulate Symfony Form and Handler behavior 
 * without needing the full Symfony Container.
 */
class MockResult {
    public function getIdentifiableObjectId() {
        return null; // Ensure we reach the render() call
    }
}

class MockHandler {
    public function handle($form) {
        return new MockResult();
    }
}

class MockBuilder {
    public function getForm() {
        return new MockForm();
    }
}

class MockForm {
    public function handleRequest($request) {}
    public function createView() {
        return "form_view_mock";
    }
}

/**
 * Test wrapper for WebserviceController to capture the parameters 
 * passed to the render method and bypass container-dependent calls.
 */
class TestWebserviceController extends WebserviceController {
    public $capturedParams = [];

    // Correct signature to match FrameworkBundleAdminController::get(string $id): object
    public function get(string $id): object {
        if (strpos($id, 'handler') !== false) {
            return new MockHandler();
        }
        if (strpos($id, 'builder') !== false) {
            return new MockBuilder();
        }
        return new \stdClass();
    }

    // Capture the parameters passed to the Twig template
    protected function render($template, $params) {
        $this->capturedParams = $params;
        return new Response();
    }

    // Correct signature to match FrameworkBundleAdminController::trans($key, $domain, array $parameters = [])
    public function trans($key, $domain, array $parameters = []) {
        return $key;
    }

    // Correct signature to match FrameworkBundleAdminController::generateSidebarLink($section, $title = false)
    public function generateSidebarLink($section, $title = false) {
        return "sidebar_link_" . $section;
    }

    // Mock flash messages
    public function addFlash($type, $message) {
        // Do nothing
    }
}

try {
    $request = new Request();
    $controller = new TestWebserviceController();
    
    // Execute the action that was modified by the fix
    $controller->createAction($request);
    
    $params = $controller->capturedParams;
    
    $hasEnableSidebar = isset($params['enableSidebar']) && $params['enableSidebar'] === true;
    $hasHelpLink = isset($params['help_link']) && !empty($params['help_link']);
    
    echo "Observed enableSidebar: " . ($hasEnableSidebar ? 'true' : 'false') . "\n";
    echo "Observed help_link: " . ($hasHelpLink ? 'present' : 'missing') . "\n";
    
    if ($hasEnableSidebar && $hasHelpLink) {
        exit(0); // Corrected
    } else {
        exit(1); // Still bugged
    }
} catch (\Throwable $t) {
    echo "Fatal Error: " . $t->getMessage() . "\n";
    exit(1);
}
