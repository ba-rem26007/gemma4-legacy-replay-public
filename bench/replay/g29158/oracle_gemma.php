<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29158, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\Product\Repository\ProductRepository;
use PrestaShop\PrestaShop\Core\Product\ProductId;
use PrestaShop\PrestaShop\Core\Product\ProductType;
use Doctrine\DBAL\DriverManager;

try {
    // 1. Créer un produit via la classe Legacy.
    // Les produits créés via ObjectModel n'ont pas de 'product_type' défini en base (NULL),
    // ce qui déclenche le bug dans le ProductRepository (Exception: Invalid product type).
    $p = new Product();
    $p->price = 10.0;
    $p->name = [1 => 'Test Product'];
    $p->link_rewrite = [1 => 'test-product'];
    $p->add();
    $id_product = (int)$p->id;

    echo "Produit créé avec ID : $id_product (product_type est NULL en base)\n";

    // 2. Récupérer les paramètres de connexion depuis l'instance Db de PrestaShop
    // pour éviter l'utilisation de constantes qui peuvent être indéfinies en CLI.
    $db = Db::getInstance();
    $connectionParams = [
        'dbname' => $db->dbname,
        'user' => $db->username,
        'password' => $db->password,
        'host' => $db->server,
        'driver' => 'pdo_mysql',
    ];
    $connection = DriverManager::getConnection($connectionParams);

    // 3. Instanciation du ProductRepository.
    // On utilise la Reflection pour injecter uniquement les dépendances nécessaires 
    // à l'exécution de getProductType() et get(), évitant ainsi de devoir instancier 
    // tout l'arbre des dépendances (Validator, Repositories, etc.).
    $repo = (new ReflectionClass(ProductRepository::class))->newInstanceWithoutConstructor();
    
    $refConnection = new ReflectionProperty(ProductRepository::class, 'connection');
    $refConnection->setAccessible(true);
    $refConnection->setValue($repo, $connection);
    
    $refPrefix = new ReflectionProperty(ProductRepository::class, 'dbPrefix');
    $refPrefix->setAccessible(true);
    $refPrefix->setValue($repo, _DB_PREFIX_);

    // 4. Appel de la méthode touchée par le correctif.
    // Avant correctif : l'absence de 'product_type' en base provoque une exception 
    // car le code tente d'instancier 'new ProductType(null)'.
    // Après correctif : le code détecte le vide et appelle getDynamicProductType().
    $productId = new ProductId($id_product);
    echo "Appel de getProductType()...\n";
    
    $productType = $repo->getProductType($productId);
    
    echo "Type de produit récupéré : " . $productType->getValue() . "\n";
    
    // Si on arrive ici sans exception, le comportement est corrigé.
    exit(0);

} catch (\Throwable $t) {
    echo "ERREUR : " . $t->getMessage() . "\n";
    echo "Fichier : " . $t->getFile() . " ligne " . $t->getLine() . "\n";
    exit(1);
}
