<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #35234, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Ensure the interface exists to avoid Fatal Error if autoloader fails in CLI.
 * We check existence first to avoid "Cannot declare interface" crash.
 */
if (!interface_exists('PrestaShop\PrestaShop\Core\Grid\HookDispatcherInterface')) {
    namespace PrestaShop\PrestaShop\Core\Grid {
        interface HookDispatcherInterface {
            public function dispatch($hookName, array $params = []);
        }
    }
}

namespace {
    use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\CustomerViewedProductGridDefinitionFactory;
    use PrestaShop\PrestaShop\Core\Grid\HookDispatcherInterface;

    /**
     * Dummy implementation of HookDispatcherInterface
     */
    class DummyDispatcher implements HookDispatcherInterface
    {
        public function dispatch($hookName, array $params = [])
        {
            return [];
        }
    }

    /**
     * Wrapper to expose the protected getColumns() method and override trans()
     * to avoid dependencies on the Symfony translation service in CLI.
     */
    class TestCustomerViewedProductGridDefinitionFactory extends CustomerViewedProductGridDefinitionFactory
    {
        public function trans($id, array $parameters = [], $domain = 'Admin.Global')
        {
            return $id;
        }

        public function exposeGetColumns()
        {
            return $this->getColumns();
        }
    }

    // Setup Context
    Context::getContext()->shop = new Shop(1);
    Context::getContext()->language = new Language(1);

    try {
        // Ensure demo data exists
        $customer = new Customer(1);
        if (!Validate::isLoadedObject($customer)) {
            $c = new Customer();
            $c->firstname = 'John';
            $c->lastname = 'Doe';
            $c->email = 'pub@prestashop.com';
            $c->passwd = 'password';
            $c->add();
        }

        // Instantiate the factory directly
        $dispatcher = new DummyDispatcher();
        $factory = new TestCustomerViewedProductGridDefinitionFactory($dispatcher, 'Y-m-d');
        
        // Get the column collection
        $columnsCollection = $factory->exposeGetColumns();

        $foundRoute = null;
        
        // Convert collection to array for iteration safely
        $columns = [];
        if (is_array($columnsCollection)) {
            $columns = $columnsCollection;
        } elseif ($columnsCollection instanceof \Traversable) {
            $columns = iterator_to_array($columnsCollection);
        }

        foreach ($columns as $column) {
            if (is_object($column) && method_exists($column, 'getOptions')) {
                $options = $column->getOptions();
                if (isset($options['field']) && $options['field'] === 'product_name') {
                    $foundRoute = $options['route'] ?? null;
                    break;
                }
            }
        }

        echo "Route observed for 'product_name' column: " . ($foundRoute ?: 'NOT FOUND') . "\n";

        // The bug is using 'admin_products_v2_preview' instead of 'admin_products_preview'
        if ($foundRoute === 'admin_products_preview') {
            echo "SUCCESS: Correct route 'admin_products_preview' is used.\n";
            exit(0);
        } elseif ($foundRoute === 'admin_products_v2_preview') {
            echo "FAILURE: Buggy route 'admin_products_v2_preview' is still used.\n";
            exit(1);
        } else {
            echo "FAILURE: Route not found or unexpected value: " . var_export($foundRoute, true) . "\n";
            exit(1);
        }

    } catch (\Throwable $t) {
        echo "EXCEPTION: " . $t->getMessage() . "\n";
        echo $t->getTraceAsString() . "\n";
        exit(1);
    }
}
