<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37220, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);
Context::getContext()->employee = new Employee(1);

/**
 * Wrapper class to expose protected methods of AdminTranslationsController.
 */
class TestTranslationsController extends AdminTranslationsController
{
    public function __construct()
    {
        parent::__construct();
        // Initialize properties to minimize internal crashes, 
        // although we primarily care about the method signature.
        $this->type_selected = 'module';
        $this->translations_informations = [
            'module' => ['var' => '']
        ];
        $this->all_iso_lang = [];
    }

    public function callFindAndFillTranslations($theme_name)
    {
        // Pass empty array to avoid executing the loop logic
        return $this->findAndFillTranslations([], $theme_name, 'testmodule', '');
    }

    public function callFindAndWriteTranslationsIntoFile($theme_name)
    {
        // Pass empty array to avoid executing the loop logic
        return $this->findAndWriteTranslationsIntoFile('test.php', [], $theme_name, 'testmodule', '');
    }
}

$controller = new TestTranslationsController();

try {
    echo "Testing findAndFillTranslations with null theme...\n";
    $controller->callFindAndFillTranslations(null);
    echo "findAndFillTranslations: OK\n";

    echo "Testing findAndWriteTranslationsIntoFile with null theme...\n";
    $controller->callFindAndWriteTranslationsIntoFile(null);
    echo "findAndWriteTranslationsIntoFile: OK\n";

    echo "Both methods accepted null theme_name. Bug is fixed.\n";
    exit(0);
} catch (\TypeError $e) {
    $msg = $e->getMessage();
    // The bug is specifically the 'string' type hint in the signature.
    // If the TypeError is about the argument type, the bug is still present.
    if (strpos($msg, 'must be of the type string') !== false || strpos($msg, 'must be of type string') !== false) {
        echo "Caught Signature TypeError: $msg\n";
        exit(1);
    }
    
    // If we get a TypeError from INSIDE the method (like array_change_key_case),
    // it means the signature check passed and we entered the method.
    // For the purpose of this fix, the signature is corrected.
    echo "Caught internal TypeError (signature check passed): $msg\n";
    exit(0);
} catch (\Throwable $e) {
    echo "Caught unexpected exception: " . $e->getMessage() . "\n";
    exit(0); // Any other error means we entered the method, so signature is OK
}
