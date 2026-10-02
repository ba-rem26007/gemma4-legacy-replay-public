<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29909, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Entity\Repository\StockRepository;
use PrestaShop\PrestaShop\Adapter\Context\ContextAdapter;
use PrestaShop\PrestaShop\Adapter\Image\ImageManager;
use PrestaShop\PrestaShop\Adapter\Stock\StockManager;

// 1. Setup Multishop environment
Configuration::updateValue('PS_MULTISHOP', 1);

$idLang = 1;
$idProduct = 1;

// Ensure Shop 2 exists with required fields
$shop2 = new Shop(2);
if (!Validate::isLoadedObject($shop2)) {
    $shop2 = new Shop();
    $shop2->id_shop_group = 1;
    $shop2->name = 'Test Shop 2';
    $shop2->active = 1;
    $shop2->id_category = 2; 
    $shop2->add();
}
$idShop2 = (int)$shop2->id;

// 2. Prepare Product data using PrestaShop classes to avoid SQL errors
// We use Product 1 from demo data or create it.
$p = new Product($idProduct);
if (!Validate::isLoadedObject($p)) {
    $p = new Product();
    $p->price = 10;
    $p->add();
    $idProduct = (int)$p->id;
}

// Set name for Shop 1
Shop::setContext(Shop::CONTEXT_SHOP, 1);
$p = new Product($idProduct);
$p->name = [$idLang => 'Name Shop 1'];
$p->save();

// Set name for Shop 2
Shop::setContext(Shop::CONTEXT_SHOP, $idShop2);
$p = new Product($idProduct);
$p->name = [$idLang => 'Name Shop 2'];
$p->save();

// 3. Instantiate StockRepository
// We use a proxy to access the protected selectSql method.
class TestStockRepository extends StockRepository {
    public function publicSelectSql($andWhere = '', $having = '', $orderBy = null) {
        return $this->selectSql($andWhere, $having, $orderBy);
    }
    
    // Override to ensure no DBAL calls are made during string construction
    protected function orderByProductIds() {
        return 'p.id_product ASC';
    }
}

$repo = new TestStockRepository(
    null, // ContainerInterface
    null, // Connection
    null, // EntityManager
    new ContextAdapter(),
    new ImageManager(),
    new StockManager(),
    _DB_PREFIX_
);

// 4. Execute the query for Shop 2
// We filter by our specific product to isolate the test.
$sql = $repo->publicSelectSql(' AND p.id_product = ' . (int)$idProduct);

// Replace Symfony/Doctrine placeholders with actual values for Shop 2
$sql = str_replace('{table_prefix}', _DB_PREFIX_, $sql);
$sql = str_replace(':shop_id', $idShop2, $sql);
$sql = str_replace(':language_id', $idLang, $sql);
$sql = str_replace(':stock_shop_id', $idShop2, $sql);
$sql = str_replace(':stock_group_id', 1, $sql);

$results = Db::getInstance()->executeS($sql);

// 5. Analysis
$foundNames = [];
if ($results) {
    foreach ($results as $row) {
        if (isset($row['name'])) {
            $foundNames[] = $row['name'];
        }
    }
}

echo "Shop 2 query results names: " . implode(', ', $foundNames) . "\n";

// The bug: without the pl.id_shop filter in the JOIN, the query returns 
// all translations for the product across all shops.
// If fixed, we should get exactly 1 row with the name for Shop 2.
$isCorrect = (count($foundNames) === 1 && $foundNames[0] === 'Name Shop 2');

if (!$isCorrect) {
    echo "BUG: Expected exactly 1 result ('Name Shop 2'), but found " . count($foundNames) . " results.\n";
    exit(1);
}

echo "SUCCESS: Only the correct name for Shop 2 was retrieved.\n";
exit(0);
