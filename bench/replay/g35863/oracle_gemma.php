<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #35863, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Controller\Admin\Sell\Catalog\AttributeController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// Setup Context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

/**
 * Mock of AttributeController to bypass Symfony Container and Twig rendering
 * while executing the actual logic of editAction.
 */
class MockAttributeController extends AttributeController
{
    public $capturedLayoutTitle = '';

    // Match the signature of FrameworkBundleAdminController::get(string $id): object
    public function get(string $id): object
    {
        if (strpos($id, 'builder') !== false) {
            return new class {
                public function getFormFor($id, $data, $options) {
                    return new class {
                        public function handleRequest($request) { return $this; }
                        public function getData() {
                            return ['name' => [1 => 'Blue']];
                        }
                    };
                }
            };
        }
        if (strpos($id, 'handler') !== false) {
            return new class {
                public function handleFor($id, $form) {
                    return new class {
                        public function getIdentifiableObjectId() { return null; }
                    };
                }
            };
        }
        return new stdClass();
    }

    // Mock the translation method to return the string with replaced parameters
    public function trans($id, $domain, $parameters = [])
    {
        $text = $id;
        foreach ($parameters as $key => $value) {
            $text = str_replace($key, $value, $text);
        }
        return $text;
    }

    // Mock the render method to capture the layoutTitle
    public function render($template, $parameters = [])
    {
        $this->capturedLayoutTitle = $parameters['layoutTitle'] ?? '';
        return new Response('ok');
    }

    // Match the signature of AbstractController::addFlash(string $type, mixed $message): void
    public function addFlash(string $type, $message): void
    {
        // Do nothing
    }

    // Mock the helper method used in the controller
    public function getContextLangId()
    {
        return 1;
    }
}

try {
    $attributeGroupId = 1;
    $attributeId = 1;

    $controller = new MockAttributeController();
    $request = new Request();

    // Call the method touched by the fix
    $controller->editAction($request, $attributeId, $attributeGroupId);

    echo "Observed layoutTitle: " . $controller->capturedLayoutTitle . "\n";

    // The fix changes 'Editing attribute %name%' to 'Editing attribute value %name%'
    // With %name% = 'Blue', the expected result is 'Editing attribute value Blue'
    if ($controller->capturedLayoutTitle === 'Editing attribute value Blue') {
        exit(0);
    } else {
        echo "Expected: Editing attribute value Blue\n";
        exit(1);
    }
} catch (\Throwable $t) {
    echo "Fatal Error: " . $t->getMessage() . "\n";
    exit(1);
}
