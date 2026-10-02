<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #32906, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\ProductGridDefinitionFactory;
use PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollection;

/**
 * The bug is that the 'id_category' filter is missing from the ProductGridDefinitionFactory,
 * which prevents the grid from knowing how to handle/clear the category filter.
 * 
 * The previous attempts failed because getFilters() calls $this->trans() and 
 * $this->configuration->get(), which require dependencies injected via the constructor.
 */

/**
 * Dummy class to simulate dependencies and avoid "Call to a member function X on null"
 */
class DummyDependency {
    public function get($key, $default = null) {
        return $default;
    }
    public function __call($name, $args) {
        return $this;
    }
}

/**
 * Proxy class to override the translation method which is called during filter definition
 */
class TestProductGridDefinitionFactory extends ProductGridDefinitionFactory
{
    protected function trans($id, $parameters = [], $domain = 'Admin.Catalog.Help')
    {
        return $id;
    }
}

// Setup Context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

try {
    // Instantiate the proxy class without calling the constructor to avoid type-hinting issues with DummyDependency
    $reflection = new ReflectionClass('TestProductGridDefinitionFactory');
    $factory = $reflection->newInstanceWithoutConstructor();

    // Inject dummy dependencies into the properties used by getFilters()
    // We search through the class hierarchy to find the properties
    $dependencies = [
        'configuration', 
        'hookDispatcher', 
        'multistoreFeature', 
        'shopConstraintContext', 
        'formFactory', 
        'singleShopChecker', 
        'multipleShopsChecker'
    ];

    foreach ($dependencies as $propName) {
        $classRef = new ReflectionClass($factory);
        while ($classRef) {
            if ($classRef->hasProperty($propName)) {
                $property = $classRef->getProperty($propName);
                $property->setAccessible(true);
                $property->setValue($factory, new DummyDependency());
                break;
            }
            $classRef = $classRef->getParentClass();
        }
    }

    // Access the protected getFilters method
    $method = $reflection->getMethod('getFilters');
    $method->setAccessible(true);

    /** @var FilterCollection $filtersCollection */
    $filtersCollection = $method->invoke($factory);

    $foundIdCategory = false;
    
    // FilterCollection is an iterator of filters
    foreach ($filtersCollection as $filter) {
        // The fix adds: new HiddenFilter('id_category')
        // We check if the filter name is 'id_category'
        if (method_exists($filter, 'getName') && $filter->getName() === 'id_category') {
            $foundIdCategory = true;
            break;
        }
    }

    if ($foundIdCategory) {
        echo "SUCCESS: Filter 'id_category' is present in the Product Grid definition.\n";
        exit(0);
    } else {
        echo "FAILURE: Filter 'id_category' is missing from the Product Grid definition.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "An error occurred during the test: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
    exit(1);
}
