<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37191, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductLazyArray;

/**
 * Test for Ticket: Specific References not visible in version 8.1.7
 * 
 * The bug is in ProductLazyArray::getSpecificReferences().
 * It returns null if 'cart_quantity' is present in the product data array.
 * 
 * To avoid the complex constructor of ProductLazyArray (which requires many dependencies),
 * we use a testable subclass and a dummy translator to prevent "Call to a member function trans() on null".
 */

class TestProductLazyArray extends ProductLazyArray
{
    public function __construct(array $product)
    {
        // We manually set the product data
        $this->product = $product;

        // To prevent "Call to a member function trans() on null", we inject a dummy translator.
        // We use Reflection because the translator property might be private/protected.
        $reflector = new ReflectionClass(ProductLazyArray::class);
        if ($reflector->hasProperty('translator')) {
            $property = $reflector->getProperty('translator');
            $property->setAccessible(true);
            $property->setValue($this, new class {
                public function trans($id, $parameters = [], $domain = null) {
                    return $id;
                }
            });
        }
    }

    /**
     * Override to ensure no real translation service is called.
     */
    public function getTranslatedKey($key)
    {
        return 'translated_' . $key;
    }
}

// 1. Setup: Ensure product 1 has a specific reference (ISBN)
$p = new Product(1);
$p->isbn = '1234567890';
$p->upc = '9876543210';
$p->save();

// 2. Prepare the data array that mimics the product presenter data.
// The bug is triggered specifically when 'cart_quantity' is present in the array.
$productData = [
    'isbn' => '1234567890',
    'upc' => '9876543210',
    'cart_quantity' => 1, // This key triggers the bug in v8.1.7
];

try {
    // Instantiate our testable version of the presenter
    $presenter = new TestProductLazyArray($productData);
    
    // Call the method touched by the fix
    $refs = $presenter->getSpecificReferences();

    echo "Observed references: " . json_encode($refs) . "\n";

    // If the bug is present, $refs will be null because 'cart_quantity' is set.
    if ($refs === null) {
        echo "FAIL: getSpecificReferences() returned null. Bug is still present.\n";
        exit(1);
    }

    // Check if the ISBN we set is actually present in the returned array
    $found = false;
    if (is_array($refs)) {
        foreach ($refs as $label => $value) {
            if ($value === '1234567890') {
                $found = true;
                break;
            }
        }
    }

    if (!$found) {
        echo "FAIL: Specific references were not found in the result.\n";
        exit(1);
    }

    echo "SUCCESS: Specific references are visible even when cart_quantity is set.\n";
    exit(0);

} catch (\Throwable $t) {
    echo "ERROR: An exception occurred: " . $t->getMessage() . "\n";
    exit(1);
}
