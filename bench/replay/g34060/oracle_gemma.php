<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #34060, validé pre/post automatiquement
require 'config/config.inc.php';
require_once 'controllers/admin/AdminImagesController.php';

// Setup context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->employee = new Employee(1);

/**
 * We create a child class to access the protected property 'isMultipleImageFormatFeatureEnabled'
 * and simulate the state where the multiple image format feature is disabled.
 */
class AdminImagesControllerTest extends AdminImagesController
{
    public function setFeatureEnabled($val)
    {
        $this->isMultipleImageFormatFeatureEnabled = $val;
    }
}

$controller = new AdminImagesControllerTest();

// Trigger condition: Feature is disabled
$controller->setFeatureEnabled(false);

// Trigger condition: PS_IMAGE_FORMAT is missing from POST
// In PHP 8+, foreach(null) throws a TypeError.
unset($_POST['PS_IMAGE_FORMAT']);

try {
    echo "Testing beforeUpdateOptions with feature disabled and missing PS_IMAGE_FORMAT in POST...\n";
    
    // This method should return early if the fix is applied.
    // If the fix is missing, it will attempt to iterate over $_POST['PS_IMAGE_FORMAT'],
    // causing a TypeError/Exception on PHP 8.0+.
    $controller->beforeUpdateOptions();
    
    echo "Success: No exception thrown. The fix is working.\n";
    exit(0);
} catch (\Throwable $t) {
    echo "Caught exception: " . $t->getMessage() . "\n";
    echo "The bug is still present (exception thrown when saving image settings).\n";
    exit(1);
}
