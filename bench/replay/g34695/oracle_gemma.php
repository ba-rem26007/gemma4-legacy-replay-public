<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #34695, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductLazyArray;

/**
 * The bug is that products with 0 quantity and no set availability date 
 * show "0000-00-00" on the Front Office.
 * The fix introduces a private method `prepareAvailabilityDate` in ProductLazyArray 
 * to sanitize this date.
 */

try {
    // We use Reflection to test the private method introduced by the fix.
    // If the method does not exist, we are on the old code, and the test should fail.
    $reflection = new ReflectionClass(ProductLazyArray::class);
    
    if (!$reflection->hasMethod('prepareAvailabilityDate')) {
        echo "Method prepareAvailabilityDate not found. This is the old code.\n";
        exit(1);
    }

    $instance = $reflection->newInstanceWithoutConstructor();
    $method = $reflection->getMethod('prepareAvailabilityDate');
    $method->setAccessible(true);

    // Test Case 1: The bug - '0000-00-00' should be converted to null
    $productBug = ['available_date' => '0000-00-00'];
    $resultBug = $method->invoke($instance, $productBug);
    echo "Input '0000-00-00' -> Result: " . var_export($resultBug, true) . "\n";

    // Test Case 2: Empty date should be null
    $productEmpty = ['available_date' => ''];
    $resultEmpty = $method->invoke($instance, $productEmpty);
    echo "Input '' -> Result: " . var_export($resultEmpty, true) . "\n";

    // Test Case 3: Invalid date format should be null
    $productInvalid = ['available_date' => 'not-a-date'];
    $resultInvalid = $method->invoke($instance, $productInvalid);
    echo "Input 'not-a-date' -> Result: " . var_export($resultInvalid, true) . "\n";

    // Test Case 4: Past date should be null
    $productPast = ['available_date' => '2000-01-01'];
    $resultPast = $method->invoke($instance, $productPast);
    echo "Input '2000-01-01' -> Result: " . var_export($resultPast, true) . "\n";

    // Test Case 5: Future date should be preserved
    $futureDate = '2099-12-31';
    $productFuture = ['available_date' => $futureDate];
    $resultFuture = $method->invoke($instance, $productFuture);
    echo "Input '$futureDate' -> Result: " . var_export($resultFuture, true) . "\n";

    // Validation
    if (
        $resultBug === null && 
        $resultEmpty === null && 
        $resultInvalid === null && 
        $resultPast === null && 
        $resultFuture === $futureDate
    ) {
        echo "All availability date validations passed.\n";
        exit(0);
    } else {
        echo "One or more validations failed.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "An error occurred: " . $t->getMessage() . "\n";
    exit(1);
}
