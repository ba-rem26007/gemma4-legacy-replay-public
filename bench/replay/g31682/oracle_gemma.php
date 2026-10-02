<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #31682, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Controller\Admin\Sell\Catalog\FeatureController;
use PrestaShop\PrestaShop\Core\Domain\Feature\Exception\InvalidFeatureIdException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

// 1. Setup: Create a Feature with required fields
$feature = new Feature();
$feature->name = [1 => 'Test Feature']; 
$feature->add();
$featureId = (int)$feature->id;

/**
 * The bug is that $featureId is passed as a string to the handler, 
 * which then throws an InvalidFeatureIdException.
 * 
 * To avoid "Call to a member function ... on null" errors from the real 
 * FeatureFormHandler's dependencies, we use a mock that specifically 
 * reproduces the type-check behavior that causes the bug.
 */
class TestFeatureController extends FeatureController
{
    public function get($id)
    {
        if ($id === 'prestashop.core.form.identifiable_object.handler.feature_form_handler') {
            return new class {
                public function handleFor($featureId, $form) {
                    // Reproduce the exact check that triggers the bug: 
                    // the handler expects an integer, not a numeric string.
                    if (!is_int($featureId)) {
                        throw new InvalidFeatureIdException(
                            sprintf("Invalid feature id '%s' supplied. Feature id must be positive integer.", $featureId)
                        );
                    }
                    return new class {
                        public function isSubmitted() { return false; }
                        public function isValid() { return false; }
                    };
                }
            };
        }
        if ($id === 'prestashop.core.form.identifiable_object.builder.feature_form_builder') {
            return new class {
                public function getFormFor($id) {
                    return new class {
                        public function handleRequest($request) {}
                    };
                }
            };
        }
        return null;
    }

    public function getQueryBus() {
        return new class {
            public function handle($cmd) {
                return new \Feature();
            }
        };
    }

    public function isFeatureEnabled() {
        return true;
    }

    public function renderEditForm(array $parameters = []) {
        return new Response('Rendered');
    }

    public function addFlash($type, $msg) {}

    public function redirectToRoute(string $route, array $parameters = [], int $status = 302): RedirectResponse
    {
        return new RedirectResponse('/');
    }
}

// 2. Execution
$controller = new TestFeatureController();
$request = new Request();

try {
    echo "Testing editAction with featureId as string: '$featureId'\n";
    
    // We simulate the route call where $featureId is passed as a string (as it comes from the URL)
    $response = $controller->editAction((string)$featureId, $request);
    
    echo "Success: No exception thrown. Response: " . $response->getContent() . "\n";
    exit(0); // Corrected: the controller now casts to (int) before calling handleFor
} catch (InvalidFeatureIdException $e) {
    echo "Bug reproduced: Caught InvalidFeatureIdException: " . $e->getMessage() . "\n";
    exit(1); // Bug still present
} catch (\Throwable $t) {
    echo "Unexpected error: " . get_class($t) . " - " . $t->getMessage() . "\n";
    exit(1);
}
