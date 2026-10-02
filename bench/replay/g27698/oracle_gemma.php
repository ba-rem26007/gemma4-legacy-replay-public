<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #27698, validé pre/post automatiquement
require 'config/config.inc.php';

use Symfony\Component\HttpFoundation\Request;
use PrestaShop\PrestaShop\Core\Search\Filters\MerchandiseReturnFilters;
use PrestaShopBundle\Controller\Admin\Sell\CustomerService\MerchandiseReturnController;
use Symfony\Component\HttpFoundation\Response;
use PrestaShop\PrestaShop\Core\Form\FormHandlerInterface;

/**
 * We create a testable version of the controller to intercept the 'render' call
 * and bypass the Symfony container which is not available in CLI.
 */
class TestMerchandiseReturnController extends MerchandiseReturnController
{
    public $capturedParams = [];

    /**
     * Intercept the render method to capture the variables passed to the Twig template.
     * Signature must match Symfony\Bundle\FrameworkBundle\Controller\AbstractController::render
     */
    public function render(string $view, array $parameters = [], ?Response $response = null): Response
    {
        $this->capturedParams = $parameters;
        return new Response();
    }

    /**
     * Mock the container 'get' method to avoid crashes and provide dummy dependencies.
     * Must return objects that satisfy the type hints in the original controller.
     */
    public function get($id)
    {
        if (strpos($id, 'grid.factory') !== false) {
            return new class {
                public function getGrid($filters)
                {
                    return new \stdClass();
                }
            };
        }

        if (strpos($id, 'form') !== false) {
            return new class implements FormHandlerInterface {
                public function getForm()
                {
                    return new class {
                        public function handleRequest($request) {}
                        public function isSubmitted() { return false; }
                        public function isValid() { return false; }
                        public function getData() { return []; }
                        public function createView() { return new \stdClass(); }
                    };
                }
                public function save($data)
                {
                    return [];
                }
            };
        }

        return new \stdClass();
    }

    /**
     * Mock presentGrid to avoid container/service dependencies.
     */
    public function presentGrid($grid)
    {
        return new \stdClass();
    }

    /**
     * Mock generateSidebarLink with the correct signature from FrameworkBundleAdminController.
     */
    public function generateSidebarLink($section, $title = false)
    {
        return 'http://prestashop-docs.com/help/' . $section;
    }
}

try {
    // 1. Setup Request with the expected legacy controller attribute
    $request = new Request();
    $request->attributes->set('_legacy_controller', 'AdminMerchandiseReturns');

    // 2. Setup Filters
    $filters = new MerchandiseReturnFilters();

    // 3. Instantiate the test controller
    $controller = new TestMerchandiseReturnController();

    // 4. Execute the action
    $controller->indexAction($request, $filters);

    // 5. Verify if 'help_link' was passed to the template
    $params = $controller->capturedParams;

    echo "Variables passed to template:\n";
    foreach ($params as $key => $value) {
        echo "- $key\n";
    }

    if (isset($params['help_link'])) {
        echo "SUCCESS: 'help_link' is present in the render parameters.\n";
        exit(0);
    } else {
        echo "FAILURE: 'help_link' is missing from the render parameters.\n";
        exit(1);
    }
} catch (\Throwable $t) {
    echo "CRITICAL ERROR: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
    exit(1);
}
