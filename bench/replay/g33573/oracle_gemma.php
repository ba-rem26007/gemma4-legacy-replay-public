<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33573, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Command\UpdateSchemaCommand;
use Symfony\Component\Console\Output\BufferedOutput;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;

// Setup Context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

$db = Db::getInstance();

try {
    // To avoid issues with undefined constants or protected properties, 
    // we use Reflection to extract the PDO instance from the existing Db connection.
    $reflection = new ReflectionClass($db);
    $property = $reflection->getProperty('connection');
    $property->setAccessible(true);
    $pdo = $property->getValue($db);

    // Create a Doctrine DBAL Connection using the existing PDO instance
    $connection = DriverManager::getConnection([
        'pdo' => $pdo,
        'driver' => 'pdo_mysql',
    ]);

    // Get DB name and prefix safely
    $dbName = $db->getValue("SELECT DATABASE()");
    $dbPrefix = defined('_DB_PREFIX_') ? _DB_PREFIX_ : 'ps_';

    /**
     * Dummy EntityManager to satisfy the constructor type-hint.
     * We override the constructor to avoid needing a full Doctrine Configuration.
     */
    $em = new class($connection) extends EntityManager {
        public function __construct($conn) {
            // Bypass parent constructor to avoid complex configuration
        }
    };

    // Create tables with a foreign key to trigger the bug in dropExistingForeignKeys
    $db->execute("CREATE TABLE IF NOT EXISTS `{$dbPrefix}test_fk_parent` (
        `id` INT AUTO_INCREMENT PRIMARY KEY
    ) ENGINE=InnoDB");

    $db->execute("CREATE TABLE IF NOT EXISTS `{$dbPrefix}test_fk_child` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `parent_id` INT,
        CONSTRAINT `fk_test_regression` FOREIGN KEY (`parent_id`) REFERENCES `{$dbPrefix}test_fk_parent`(`id`)
    ) ENGINE=InnoDB");

    $output = new BufferedOutput();
    $command = new UpdateSchemaCommand($dbName, $dbPrefix, $em);

    echo "Testing dropExistingForeignKeys...\n";
    
    // The bug: $nbQueries += $connection->executeQuery($drop);
    // executeQuery returns a Result object. Adding an int to a Result object throws a TypeError in PHP 8.
    $result = $command->dropExistingForeignKeys($connection, $output);

    echo "Success: Method returned " . $result . "\n";
    echo "Output: " . $output->fetch();

    // Cleanup
    $db->execute("DROP TABLE IF EXISTS `{$dbPrefix}test_fk_child` ");
    $db->execute("DROP TABLE IF EXISTS `{$dbPrefix}test_fk_parent` ");

    exit(0);
} catch (\Throwable $t) {
    echo "Caught error: " . get_class($t) . " - " . $t->getMessage() . "\n";
    
    // Cleanup
    if (isset($db)) {
        $dbPrefix = defined('_DB_PREFIX_') ? _DB_PREFIX_ : 'ps_';
        $db->execute("DROP TABLE IF EXISTS `{$dbPrefix}test_fk_child` ");
        $db->execute("DROP TABLE IF EXISTS `{$dbPrefix}test_fk_parent` ");
    }

    if ($t instanceof \TypeError) {
        echo "Bug reproduced: TypeError encountered during addition of Result object to integer.\n";
        exit(1);
    }
    
    exit(1);
}
