<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #34598, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\Search\SearchProductSearchProvider;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Product\Search\SortOrder;

/**
 * Mock Translator to satisfy SearchProductSearchProvider dependency
 * Signature must match Symfony\Contracts\Translation\TranslatorInterface exactly
 */
class MockTranslator implements \Symfony\Contracts\Translation\TranslatorInterface
{
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $id;
    }

    public function getLocale(): string
    {
        return 'fr';
    }

    public function setLocale(string $locale): void
    {
    }

    public function fallback($locale): void
    {
    }
}

try {
    // Setup context
    $context = Context::getContext();
    $context->shop = new Shop(1);
    $context->language = new Language(1);

    // Instantiate the provider directly
    $translator = new MockTranslator();
    $provider = new SearchProductSearchProvider($translator);

    // Create a search context
    // The constructor expects a Context object (or null)
    $searchContext = new ProductSearchContext($context);
    
    // Create a search query
    // ProductSearchQuery(searchString, page, resultsPerPage, sortOrder)
    $initialSortOrder = new SortOrder('product', 'position', 'asc');
    $query = new ProductSearchQuery('test', 1, 10, $initialSortOrder);

    // Execute the query
    // The runQuery method populates the result with available sort orders, including "Relevance"
    $result = $provider->runQuery($searchContext, $query);
    $availableSortOrders = $result->getAvailableSortOrders();

    $relevanceSortOrder = null;
    foreach ($availableSortOrders as $sortOrder) {
        // The "Relevance" sort order is defined by the field 'position'
        if ($sortOrder->getField() === 'position') {
            $relevanceSortOrder = $sortOrder;
            break;
        }
    }

    if (!$relevanceSortOrder) {
        echo "Error: Relevance sort order (field 'position') not found.\n";
        exit(1);
    }

    $orderWay = $relevanceSortOrder->getOrder();
    echo "Observed Relevance sort order: $orderWay\n";

    // The bug: Relevance was 'asc' (least relevant first).
    // The fix: Relevance should be 'desc' (most relevant first).
    if ($orderWay === 'desc') {
        echo "Success: Relevance sort order is correctly set to DESC.\n";
        exit(0);
    } else {
        echo "Failure: Relevance sort order is $orderWay, expected DESC.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Fatal Error: " . $t->getMessage() . "\n";
    exit(1);
}
