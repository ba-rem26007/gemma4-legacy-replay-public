<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #34823, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\Product\Update\ProductDuplicator;
use PrestaShop\PrestaShop\Adapter\Product\Repository\ProductRepository;
use PrestaShop\PrestaShop\Adapter\Product\Image\Repository\ProductImageRepository;
use PrestaShop\PrestaShop\Adapter\Product\Combination\Repository\CombinationRepository;
use PrestaShop\PrestaShop\Adapter\Product\Repository\ProductSupplierRepository;
use PrestaShop\PrestaShop\Adapter\Product\SpecificPrice\Repository\SpecificPriceRepository;
use PrestaShop\PrestaShop\Adapter\Product\Stock\Repository\StockAvailableRepository;
use PrestaShop\PrestaShop\Adapter\Product\Stock\Update\ProductStockUpdater;
use PrestaShop\PrestaShop\Adapter\Product\Image\ProductImagePathFactory;
use PrestaShop\PrestaShop\Core\Hook\HookDispatcher;
use PrestaShop\PrestaShop\Adapter\Core\Hook\Repository\HookRepository;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductId;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopId;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use Doctrine\DBAL\DriverManager;

try {
    // 1. Setup: Use existing product 1 and force an old date_add
    $sourceProductId = 1;
    $oldDate = '2020-01-01 00:00:00';
    
    Db::getInstance()->execute("UPDATE " . _DB_PREFIX_ . "product SET date_add = '$oldDate' WHERE id_product = " . (int)$sourceProductId);
    Db::getInstance()->execute("UPDATE " . _DB_PREFIX_ . "product_shop SET date_add = '$oldDate' WHERE id_product = " . (int)$sourceProductId . " AND id_shop = 1");

    echo "Produit source $sourceProductId date_add forcée à : $oldDate\n";

    // 2. Get Doctrine DBAL Connection by extracting the PDO instance from DbPDO via Reflection
    $db = Db::getInstance();
    $reflection = new ReflectionClass($db);
    $property = $reflection->getProperty('_connection');
    $property->setAccessible(true);
    $pdo = $property->getValue($db);
    
    $connection = DriverManager::getConnection(['pdo' => $pdo, 'driver' => 'pdo_mysql']);

    // 3. Instantiate ProductDuplicator with its dependencies
    $productRepository = new ProductRepository($connection);
    $productImageRepository = new ProductImageRepository($connection);
    $combinationRepository = new CombinationRepository($connection);
    $productSupplierRepository = new ProductSupplierRepository($connection);
    $specificPriceRepository = new SpecificPriceRepository($connection);
    $stockAvailableRepository = new StockAvailableRepository($connection);
    $productStockUpdater = new ProductStockUpdater($connection, $stockAvailableRepository);
    $productImagePathFactory = new ProductImagePathFactory();
    $hookRepository = new HookRepository($connection);
    $hookDispatcher = new HookDispatcher($hookRepository);

    $duplicator = new ProductDuplicator(
        $connection,
        $productRepository,
        $productImageRepository,
        $combinationRepository,
        $productSupplierRepository,
        $specificPriceRepository,
        $stockAvailableRepository,
        $productStockUpdater,
        $productImagePathFactory,
        $hookDispatcher
    );

    // 4. Execute duplication
    $sourceProductIdVO = new ProductId($sourceProductId);
    $shopConstraint = new ShopConstraint(new ShopId(1));
    
    $newProductIdVO = $duplicator->duplicate($sourceProductIdVO, $shopConstraint);
    $newProductId = $newProductIdVO->getValue();
    
    echo "Nouveau produit créé avec l'ID : $newProductId\n";

    // 5. Verify date_add of the duplicated product
    // The fix specifically targets the product_shop table
    $newDateProduct = Db::getInstance()->getValue("SELECT date_add FROM " . _DB_PREFIX_ . "product WHERE id_product = " . (int)$newProductId);
    $newDateShop = Db::getInstance()->getValue("SELECT date_add FROM " . _DB_PREFIX_ . "product_shop WHERE id_product = " . (int)$newProductId . " AND id_shop = 1");
    
    echo "Date_add (ps_product) : $newDateProduct\n";
    echo "Date_add (ps_product_shop) : $newDateShop\n";

    // The test fails if the new date is the same as the old date
    if ($newDateProduct === $oldDate || $newDateShop === $oldDate) {
        echo "ÉCHEC : La date_add a été copiée du produit original.\n";
        exit(1);
    }

    echo "SUCCÈS : La date_add a été mise à jour.\n";
    exit(0);

} catch (\Throwable $t) {
    echo "Erreur lors de l'exécution du test : " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
    exit(1);
}
