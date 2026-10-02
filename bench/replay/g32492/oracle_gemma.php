<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #32492, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Test for Ticket: Can't use LIMIT clause in Sql request manager
 * The bug is that checkedLimit() looks for 'start' and 'end' keys 
 * instead of 'offset' and 'rowcount'.
 */

try {
    $sql = 'SELECT id_product FROM ps_product LIMIT 0, 10';
    
    // Instantiate RequestSql
    $reqSql = new RequestSql($sql);

    // Use the internal parser to get the exact $tab structure expected by validateSql
    // This avoids "count(): Argument #1 must be of type Countable|array" errors
    // caused by manually constructing an incorrect $tab array.
    $tab = $reqSql->parsingSql($sql);
    $in = [];

    // validateSql calls checkedLimit if $tab['LIMIT'] is set
    $result = $reqSql->validateSql($tab, $in, $sql);

    echo "SQL: $sql\n";
    echo "Validation result: " . ($result ? 'TRUE' : 'FALSE') . "\n";

    if (!$result) {
        echo "Errors: ";
        print_r($reqSql->error_sql);
    }

    // If the fix is applied, validateSql should return true
    exit($result ? 0 : 1);

} catch (\Throwable $t) {
    echo "Exception: " . $t->getMessage() . "\n";
    exit(1);
}
