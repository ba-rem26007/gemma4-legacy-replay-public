<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29740, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->employee = new Employee(1);

/**
 * We create a Mock class to bypass the AdminController constructor 
 * (which triggers template loading and Symfony container dependencies)
 * and to mock the trans() method which depends on the Symfony translator.
 */
class MockTaxRulesGroupController extends AdminTaxRulesGroupController
{
    public function __construct()
    {
        // Do nothing to avoid the parent constructor's side effects in CLI
    }

    public function trans($id, array $parameters = [], $domain = null)
    {
        // Simply return the translation ID to avoid calling the Symfony translator
        return $id;
    }
}

try {
    $controller = new MockTaxRulesGroupController();
    
    // Set the context property. Since it might be protected in some versions, 
    // we use Reflection to ensure it's set.
    $reflection = new ReflectionClass('AdminTaxRulesGroupController');
    $contextProp = $reflection->getProperty('context');
    $contextProp->setAccessible(true);
    $contextProp->setValue($controller, $context);

    // Call the method that defines the form fields
    $controller->initRuleForm();
    
    if (!isset($controller->fields_form[0]['form']['input'])) {
        echo "Error: Form inputs not initialized.\n";
        exit(1);
    }

    $inputs = $controller->fields_form[0]['form']['input'];
    $taxField = null;

    // Find the input field named 'tax'
    foreach ($inputs as $input) {
        if (isset($input['name']) && $input['name'] === 'tax') {
            $taxField = $input;
            break;
        }
    }

    if ($taxField === null) {
        echo "Error: Tax input field not found in the form.\n";
        exit(1);
    }

    // Check if the incorrect hint is present
    $hint = isset($taxField['hint']) ? $taxField['hint'] : '';
    echo "Observed hint for Tax field: '$hint'\n";

    // The bug is the presence of the hint "(Total tax: 9%)"
    // If the hint contains this string, the bug is still present (fail = exit 1)
    if (strpos($hint, 'Total tax: 9%') !== false) {
        echo "Bug found: Incorrect help text is still present.\n";
        exit(1);
    }

    echo "Success: Incorrect help text has been removed.\n";
    exit(0);

} catch (\Throwable $t) {
    echo "Exception caught: " . $t->getMessage() . "\n";
    exit(1);
}
