<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #28763, validé pre/post automatiquement
require 'config/config.inc.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use PrestaShopBundle\Controller\Admin\Sell\Address\AddressController;

/**
 * Mock of AddressController to bypass Symfony container dependencies
 * and capture the parameters passed to the render method.
 */
class TestableAddressController extends AddressController
{
    public function get($id)
    {
        return new class {
            public function getFormFor($id, $data) {
                return new class {
                    public function handleRequest($r) {}
                    public function createView() { return 'form_view_mock'; }
                };
            }
            public function handleFor($id, $form) { return true; }
        };
    }

    public function getQueryBus()
    {
        return new class {
            public function handle($query) {
                return new class { };
            }
        };
    }

    public function render(string $view, array $parameters = [], ?Response $response = null): Response
    {
        // We return the parameters as the response content to verify them
        return new Response(serialize($parameters));
    }

    public function generateUrl(string $route, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return "url_for_" . $route;
    }

    public function generateSidebarLink($section, $title = false)
    {
        return "sidebar_link_mock";
    }
}

try {
    // Setup: Ensure Customer 1 and Address 1 exist
    $customer = new Customer(1);
    if (!Validate::isLoadedObject($customer)) {
        $customer = new Customer();
        $customer->firstname = 'Test';
        $customer->lastname = 'User';
        $customer->email = 'test@example.com';
        $customer->passwd = 'password123';
        $customer->add();
    }

    $address = new Address(1);
    if (!Validate::isLoadedObject($address)) {
        $address = new Address();
        $address->id_customer = $customer->id;
        $address->id_country = 1;
        $address->firstname = 'Test';
        $address->lastname = 'User';
        $address->address1 = '123 Test Street';
        $address->city = 'Test City';
        $address->alias = 'Home';
        $address->add();
    }

    // Simulate a request coming from the customer detail page with a 'back' parameter
    $backUrl = 'http://localhost/admin/customers/1';
    $request = new Request([]);
    $request->query->set('back', $backUrl);

    $controller = new TestableAddressController();
    
    // Call the method touched by the fix
    $response = $controller->editAction(1, $request);
    
    // Extract parameters passed to Twig render
    $params = unserialize($response->getContent());
    $observedCancelPath = $params['cancelPath'] ?? null;

    echo "Expected cancelPath: $backUrl\n";
    echo "Observed cancelPath: " . ($observedCancelPath ?: 'NULL') . "\n";

    // The test passes if cancelPath is correctly set to the 'back' URL
    if ($observedCancelPath === $backUrl) {
        exit(0);
    } else {
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
