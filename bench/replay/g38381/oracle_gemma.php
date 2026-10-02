<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #38381, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * The bug is that AdminController::copyFromPost used get_class_vars() to retrieve the 
 * ObjectModel definition. In PHP 8.1+, get_class_vars() does not return static properties.
 * Since $definition is a static property in PrestaShop ObjectModels, multilingual 
 * fields were ignored during the copyFromPost process.
 */

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);

// Ensure an employee is connected
$employee = new Employee(1);
if (!Validate::isLoadedObject($employee)) {
    $employee = new Employee();
    $employee->firstname = 'Test';
    $employee->lastname = 'Employee';
    $employee->email = 'test@example.com';
    $employee->passwd = 'password';
    $employee->add();
}
$context->employee = $employee;

/**
 * Create a dummy ObjectModel that overrides the definition to add a custom multilingual field.
 * This mimics the behavior of a module adding a field via override.
 */
class CustomObjectModel extends ObjectModel
{
    public $custom_tagline;

    public static $definition = [
        'table' => 'custom_object',
        'primary' => 'id_custom_object',
        'fields' => [
            'custom_tagline' => [
                'type' => self::TYPE_STRING, 
                'lang' => true, 
                'validate' => 'isGenericName', 
                'required' => false, 
                'size' => 255
            ],
        ],
    ];
}

/**
 * Helper class to expose the protected copyFromPost method of AdminController.
 */
class TestAdminController extends AdminController
{
    public function publicCopyFromPost(&$object, $table)
    {
        $this->copyFromPost($object, $table);
    }
}

// Mock POST data: a value for the custom_tagline field for language ID 1
$_POST = [];
$_POST['custom_tagline_1'] = 'Regression Test Value';

try {
    $obj = new CustomObjectModel();
    $controller = new TestAdminController();
    
    // We do not set $controller->context directly because it is protected.
    // AdminController uses Context::getContext() internally if needed.

    // Execute the method containing the bug
    $controller->publicCopyFromPost($obj, 'custom_object');

    // Check if the multilingual field was correctly populated from $_POST
    // In PrestaShop, multilingual fields are stored as arrays: [id_lang => value]
    $observed = isset($obj->custom_tagline[1]) ? $obj->custom_tagline[1] : null;
    
    echo "Observed value for custom_tagline[1]: " . ($observed ?? 'NULL') . "\n";

    if ($observed === 'Regression Test Value') {
        echo "SUCCESS: Custom lang field was correctly populated.\n";
        exit(0);
    } else {
        echo "FAILURE: Custom lang field was ignored. Bug is still present.\n";
        exit(1);
    }
} catch (\Throwable $t) {
    echo "FATAL ERROR: " . $t->getMessage() . "\n";
    exit(1);
}
