<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #34317, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\Search\SearchProductSearchProvider;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Product\Search\SortOrder;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Mock Translator to satisfy SearchProductSearchProvider dependency.
 * Signature must match Symfony\Contracts\Translation\TranslatorInterface exactly.
 */
class MockTranslator implements TranslatorInterface
{
    public function trans($id, array $parameters = [], $domain = null, $locale = null): string
    {
        return (string)$id;
    }
    public function getLocale(): string { return 'fr'; }
    public function setLocale(string $locale): void { }
    public function fallback(): void { }
}

try {
    // 1. Setup Context and Provider
    // ProductSearchContext expects a Context object (or null)
    $psContext = new ProductSearchContext(Context::getContext());
    $translator = new MockTranslator();
    $provider = new SearchProductSearchProvider($translator);

    // 2. Setup Query
    // A search string is required to enter the block where setAvailableSortOrders is called
    $query = new ProductSearchQuery();
    $query->setSearchString('test');
    $query->setPage(1);
    $query->setResultsPerPage(10);
    
    // Set a default sort order to avoid null pointer in runQuery
    $query->setSortOrder(new SortOrder('product', 'position', 'desc'));

    // 3. Execute the query
    $result = $provider->runQuery($psContext, $query);
    $availableSortOrders = $result->getAvailableSortOrders();

    // 4. Verify the "Relevance" sort order
    $relevanceSortOrder = null;
    foreach ($availableSortOrders as $sortOrder) {
        // The label is the translation of 'Relevance' (which our MockTranslator returns as is)
        if ($sortOrder->getLabel() === 'Relevance') {
            $relevanceSortOrder = $sortOrder;
            break;
        }
    }

    if (!$relevanceSortOrder) {
        echo "Error: Relevance sort order not found in available sort orders.\n";
        exit(1);
    }

    $orderWay = $relevanceSortOrder->getOrder();
    echo "Relevance sort order way: $orderWay\n";

    // The bug: Relevance was listed in reverse order (asc instead of desc).
    // The fix: Explicitly set to 'desc' for most relevant first.
    if ($orderWay === 'desc') {
        exit(0);
    } else {
        echo "Failure: Relevance sort order should be 'desc' but is '$orderWay'.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Exception: " . $t->getMessage() . "\n";
    exit(1);
}
