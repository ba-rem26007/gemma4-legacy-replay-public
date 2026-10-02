<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #35384, validé pre/post automatiquement
require 'config/config.inc.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use PrestaShopBundle\Controller\Api\StockController;
use PrestaShopBundle\Api\QueryParamsCollection;

/**
 * We extend StockController to bypass security, provide a mock container,
 * and avoid actual JSON output.
 */
class TestStockController extends StockController
{
    public function isGranted($permissions, $controller)
    {
        return true;
    }

    // Fixed signature to match PrestaShopBundle\Controller\Api\ApiController::jsonResponse
    public function jsonResponse($data, Request $request, ?QueryParamsCollection $queryParams = null, $status = 200, $headers = [])
    {
        return new Response();
    }

    // Method to inject the container since it is protected in ApiController
    public function setContainer($container)
    {
        $this->container = $container;
    }
}

/**
 * Mock for the service that processes request parameters.
 * It must return an instance of QueryParamsCollection or null to satisfy the type hint in jsonResponse.
 */
class MockQueryParams
{
    public function fromRequest($request)
    {
        // Returning null is the safest way to satisfy ?QueryParamsCollection 
        // without triggering constructor dependencies of the real class.
        return null;
    }
}

/**
 * Mock for StockRepository to avoid dependency hell
 */
class MockStockRepository
{
    public function getData($params)
    {
        return [];
    }

    public function countPages($params)
    {
        return 1;
    }
}

// 1. Setup: Create a request with multiple keywords as a comma-separated string
// This is the input that triggers the bug.
$keywordsString = 'product1,product2';
$request = new Request(['keywords' => $keywordsString]);

echo "Input keywords: " . $request->query->get('keywords') . " (type: " . gettype($request->query->get('keywords')) . ")\n";

// 2. Instantiate the controller and its dependencies
$controller = new TestStockController();
$controller->queryParams = new MockQueryParams();
$controller->stockRepository = new MockStockRepository();

// Use the helper method to set the protected container
$controller->setContainer(new class {
    public function get($id) {
        return new class {
            public function generate($route) { return 'http://localhost/dummy'; }
        };
    }
});

try {
    // 3. Call the action
    $controller->listProductsAction($request);
} catch (\Throwable $e) {
    echo "Unexpected error: " . $e->getMessage() . "\n";
    exit(1);
}

// 4. Verification
// After the fix, the 'keywords' parameter in the Request object should have been 
// converted from a string to an array.
$finalKeywords = $request->query->get('keywords');
$isNowArray = is_array($finalKeywords);

echo "Final keywords: ";
if ($isNowArray) {
    echo implode(', ', $finalKeywords) . " (type: array)\n";
} else {
    echo $finalKeywords . " (type: " . gettype($finalKeywords) . ")\n";
}

if ($isNowArray && count($finalKeywords) === 2 && $finalKeywords[0] === 'product1' && $finalKeywords[1] === 'product2') {
    echo "SUCCESS: Keywords were correctly converted to an array.\n";
    exit(0);
} else {
    echo "FAILURE: Keywords are still a string or incorrectly processed.\n";
    exit(1);
}
