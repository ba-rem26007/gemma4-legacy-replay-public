<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #28237, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Test for Ticket: When an Exception is thrown with a string code, there is a fatal error.
 * 
 * The bug occurs because PDOException can return a string for getCode() (SQLSTATE),
 * but PrestaShopException (which extends Exception) requires an integer.
 * Before the fix, PDOException bubbled up and was likely handled by a mechanism 
 * that tried to instantiate a PrestaShopException using the string code, causing a TypeError.
 * 
 * The fix wraps the PDO query in a try-catch and casts the code to (int).
 */

echo "Testing PDOException to PrestaShopException conversion...\n";

try {
    // We trigger a SQL error by selecting a non-existent column.
    // This will cause PDO::query() to throw a PDOException with a string code (e.g., '42S22').
    Db::getInstance()->execute('SELECT * FROM ' . _DB_PREFIX_ . 'product WHERE non_existent_column_test = 1');
} catch (\PrestaShopException $e) {
    // If the fix is applied, the PDOException is caught in DbPDO::_query 
    // and re-thrown as a PrestaShopException with an integer code.
    echo "Result: Caught PrestaShopException. Fix is working.\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . " (Type: " . gettype($e->getCode()) . ")\n";
    exit(0);
} catch (\PDOException $e) {
    // If the fix is NOT applied, the PDOException bubbles up directly.
    echo "Result: Caught PDOException. Fix is NOT applied.\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . " (Type: " . gettype($e->getCode()) . ")\n";
    exit(1);
} catch (\Throwable $t) {
    // If a TypeError occurs (the "fatal error" mentioned in the ticket), it will be caught here.
    echo "Result: Caught " . get_class($t) . ". This is likely the fatal error (TypeError).\n";
    echo "Message: " . $t->getMessage() . "\n";
    exit(1);
}
