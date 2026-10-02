<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29571, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);
$employee = new Employee(1);
if (!Validate::isLoadedObject($employee)) {
    $employee = new Employee();
    $employee->firstname = 'Test';
    $employee->lastname = 'User';
    $employee->email = 'test@example.com';
    $employee->passwd = 'passwd';
    $employee->add();
}
Context::getContext()->employee = $employee;

try {
    // 1. Initial state: Create an alias 'blue' for the search term 'color'
    $initialAlias = new Alias();
    $initialAlias->alias = 'blue';
    $initialAlias->search = 'color';
    $initialAlias->active = 1;
    $initialAlias->add();
    
    $idInitial = $initialAlias->id;
    echo "Initial alias created: blue -> color (ID: $idInitial)\n";

    // 2. Simulate the 'Edit' action: Change 'blue' to 'red' for the same search term 'color'
    // The controller uses Tools::getValue which reads from $_POST
    $_POST['search'] = 'color';
    $_POST['alias'] = 'red';
    $_POST['id_alias'] = $idInitial; // Simulating the edit mode

    $controller = new AdminSearchConfController();
    $controller->processSave();

    // 3. Verification
    // We check how many aliases are now associated with the search term 'color'
    $results = Db::getInstance()->executeS('SELECT alias FROM ' . _DB_PREFIX_ . 'alias WHERE search = "color"');
    $count = count($results);
    $aliasesFound = [];
    foreach ($results as $row) {
        $aliasesFound[] = $row['alias'];
    }

    echo "Aliases found for 'color': " . implode(', ', $aliasesFound) . " (Count: $count)\n";

    // BUG: Before the fix, 'blue' remains and 'red' is added (Count = 2)
    // FIXED: Only 'red' should exist (Count = 1)
    if ($count === 1 && in_array('red', $aliasesFound) && !in_array('blue', $aliasesFound)) {
        echo "SUCCESS: Alias updated correctly.\n";
        exit(0);
    } else {
        echo "FAILURE: Alias duplicated or not updated. Found: " . implode(', ', $aliasesFound) . "\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
