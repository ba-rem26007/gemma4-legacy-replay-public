<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #28973, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Form\Admin\Configure\ShopParameters\ProductPreferences\PaginationFormDataProvider;

/**
 * Mock for Translator to avoid Interface not found errors.
 * We avoid implementing any Symfony interfaces to prevent "Interface not found" fatal errors.
 */
class MockTranslator
{
    public function trans($id, array $parameters = [], string $domain = null): string
    {
        return $id;
    }
}

/**
 * Mock for PaginationConfiguration.
 * We avoid extending the real class to prevent "Class not found" fatal errors.
 */
class MockConfiguration
{
    public function updateConfiguration(array $data)
    {
        return true;
    }

    public function getConfiguration()
    {
        return [];
    }
}

try {
    // Setup Context
    Context::getContext()->shop = new Shop(1);
    Context::getContext()->language = new Language(1);

    // Use Reflection to instantiate PaginationFormDataProvider without calling the constructor.
    // This bypasses the type-hint checks for PaginationConfiguration and TranslatorInterface.
    $reflectionClass = new ReflectionClass(PaginationFormDataProvider::class);
    $provider = $reflectionClass->newInstanceWithoutConstructor();

    // Inject dependencies via Reflection into private properties
    $refConfig = new ReflectionProperty(PaginationFormDataProvider::class, 'configuration');
    $refConfig->setAccessible(true);
    $refConfig->setValue($provider, new MockConfiguration());

    $refTranslator = new ReflectionProperty(PaginationFormDataProvider::class, 'translator');
    $refTranslator->setAccessible(true);
    $refTranslator->setValue($provider, new MockTranslator());

    // Test case: products_per_page = 0
    // Before fix: 0 is allowed (0 > 0 is false), so validate() returns empty array.
    // After fix: 0 is NOT allowed (0 >= 0 is true), so validate() returns an error.
    $data = ['products_per_page' => 0];
    $result = $provider->setData($data);

    echo "Input: products_per_page = 0\n";
    echo "Result: ";
    var_dump($result);

    if (is_array($result) && isset($result[0]['key'])) {
        $errorMessage = $result[0]['key'];
        echo "Observed error key: $errorMessage\n";

        // The fix changes the error message to include "Please enter a positive integer."
        if (strpos($errorMessage, 'positive integer') !== false) {
            echo "SUCCESS: Validation correctly rejects 0 with the new error message.\n";
            exit(0);
        } else {
            echo "FAILURE: Validation rejected 0 but the error message is the old one: $errorMessage\n";
            exit(1);
        }
    } else {
        echo "FAILURE: Validation allowed the value 0 (no errors returned). The bug is still present.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "FATAL ERROR: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
    exit(1);
}
