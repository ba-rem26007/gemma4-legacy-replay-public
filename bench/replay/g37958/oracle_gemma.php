<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37958, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Since the autoloader is failing to find the Grid Filter classes in the CLI environment,
 * we define the necessary mocks in the expected namespaces.
 * This ensures that when ProductController calls 'new ProductFilters(...)', it uses these mocks.
 */
namespace PrestaShop\PrestaShop\Core\Grid\Filter {
    class ShopConstraint {
        public static function allShops() {
            return new self();
        }
    }

    class ProductFilters {
        private $shopConstraint;
        private $filters;

        public function __construct($shopConstraint, array $filters = []) {
            $this->shopConstraint = $shopConstraint;
            $this->filters = $filters;
        }

        public function getShopConstraint() {
            return $this->shopConstraint;
        }

        public function all() {
            return $this->filters;
        }
    }
}

namespace {
    use PrestaShop\PrestaShop\Core\Grid\Filter\ProductFilters;
    use PrestaShop\PrestaShop\Core\Grid\Filter\ShopConstraint;
    use PrestaShopBundle\Controller\Admin\Sell\Catalog\Product\ProductController;

    /**
     * Mock for the Grid data to avoid calling real database/grid logic
     */
    class MockGridData {
        public function getRecords() {
            return new class {
                public function all() {
                    return [];
                }
            };
        }
    }

    class MockGrid {
        public function getData() {
            return new MockGridData();
        }
    }

    /**
     * Mock for the Grid Factory to capture the filters passed to it
     */
    class MockProductGridFactory {
        public static $capturedFilters = null;

        public function getGrid($filters) {
            self::$capturedFilters = $filters;
            return new MockGrid();
        }
    }

    /**
     * Test Controller to override the Symfony container 'get' method
     * and translation method to avoid dependencies on the Symfony Kernel.
     */
    class TestProductController extends ProductController {
        public function get($id) {
            if ($id === 'prestashop.core.grid.factory.product') {
                return new MockProductGridFactory();
            }
            return null; 
        }

        public function trans($id, $domain = 'Admin.Global', $locale = null) {
            return $id;
        }
    }

    try {
        // 1. Setup Context
        Context::getContext()->shop = new Shop(1);
        Context::getContext()->language = new Language(1);

        // 2. Create ProductFilters with a specific limit
        // This simulates a user being on page 2 with a limit of 50 items per page.
        $initialLimit = 50;
        $initialFiltersArray = ['limit' => $initialLimit, 'page' => 2];
        $filters = new ProductFilters(ShopConstraint::allShops(), $initialFiltersArray);

        // 3. Execute the export action
        $controller = new TestProductController();
        $controller->exportAction($filters);

        // 4. Analyze the filters that actually reached the Grid Factory
        $receivedFilters = MockProductGridFactory::$capturedFilters;
        
        if (!$receivedFilters instanceof ProductFilters) {
            echo "Error: Grid Factory did not receive a ProductFilters object.\n";
            exit(1);
        }

        $finalFiltersArray = $receivedFilters->all();
        $finalLimit = isset($finalFiltersArray['limit']) ? $finalFiltersArray['limit'] : null;

        echo "Initial limit requested: $initialLimit\n";
        echo "Limit received by Grid Factory: " . ($finalLimit === null ? 'null' : $finalLimit) . "\n";

        // The fix is: $filters = new ProductFilters($filters->getShopConstraint(), ['limit' => null] + $filters->all());
        // Therefore, the limit MUST be null for the export to include all products.
        if ($finalLimit === null) {
            echo "SUCCESS: Limit was correctly nullified for export.\n";
            exit(0);
        } else {
            echo "FAILURE: Limit is still $finalLimit. Only the first page will be exported.\n";
            exit(1);
        }

    } catch (\Throwable $t) {
        echo "EXCEPTION: " . $t->getMessage() . "\n";
        echo $t->getTraceAsString() . "\n";
        exit(1);
    }
}
