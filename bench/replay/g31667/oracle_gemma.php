<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #31667, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\SqlManager\SqlQueryValidator;

/**
 * The bug is that on PHP 8, the SqlQueryValidator fails to catch a PrestaShopException
 * thrown by the parser when a malformed query is provided.
 * 
 * The previous tests passed on old code because the queries used likely triggered 
 * an ErrorException or PrestaShopDatabaseException, which were already caught.
 * 
 * To trigger the bug, we need a query that specifically throws a PrestaShopException.
 * According to the ticket, an alias in the wrong position (near SELECT *) is the trigger.
 * We use the exact query from the ticket.
 */

$validator = new SqlQueryValidator();

// Exact query from the ticket that is reported to cause the exception on PHP 8
$sql = "SELECT * CUST FROM hana_customer WHERE id_customer = 2";

try {
    echo "Testing SQL: $sql\n";
    $errors = $validator->validate($sql);
    
    if (empty($errors)) {
        echo "Failure: No errors returned for a bad query.\n";
        exit(1);
    }

    $foundBadQueryError = false;
    foreach ($errors as $error) {
        if (isset($error['key']) && $error['key'] === 'Bad SQL query') {
            $foundBadQueryError = true;
            break;
        }
    }

    if (!$foundBadQueryError) {
        echo "Failure: The validator returned errors, but not the expected 'Bad SQL query' notification.\n";
        echo "Observed errors: " . json_encode($errors) . "\n";
        exit(1);
    }
    
    echo "Success: 'Bad SQL query' error was correctly caught and returned.\n";
    exit(0);

} catch (\Throwable $t) {
    // If we catch an exception here, it means the SqlQueryValidator::validate() 
    // method failed to catch it internally. This is the bug.
    echo "Failure: An uncaught exception was thrown by the validator: " . get_class($t) . " - " . $t->getMessage() . "\n";
    exit(1);
}
