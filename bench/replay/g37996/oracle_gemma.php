<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37996, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\Tab\TabDataProvider;
use PrestaShop\PrestaShop\Adapter\LegacyContext;
use PrestaShop\PrestaShop\Adapter\Configuration;

try {
    // Setup context
    $context = Context::getContext();
    $context->shop = new Shop(1);
    $context->language = new Language(1);
    $context->currency = new Currency(1);
    
    // Ensure employee 1 exists for the context
    $employee = new Employee(1);
    if (!Validate::isLoadedObject($employee)) {
        $employee = new Employee();
        $employee->firstname = 'Test';
        $employee->lastname = 'Employee';
        $employee->email = 'test@example.com';
        $employee->passwd = 'passwd';
        $employee->id_profile = 1; // SuperAdmin
        $employee->active = 1;
        $employee->add();
    }
    $context->employee = $employee;

    // Instantiate Symfony adapter classes directly
    $legacyContext = new LegacyContext($context);
    $configuration = new Configuration();
    $dataProvider = new TabDataProvider($legacyContext, $configuration);

    // Create a parent tab that is INACTIVE
    $parentTab = new Tab();
    $parentTab->class_name = 'AdminMore';
    $parentTab->active = 0;
    $parentTab->enabled = 1;
    $parentTab->id_parent = 0;
    $parentTab->position = 1;
    $parentTab->name = [1 => 'Parent Tab']; // Required for validation
    $parentTab->add();

    // Create a child tab that is ACTIVE
    $childTab = new Tab();
    $childTab->class_name = 'AdminChildPage';
    $childTab->active = 1;
    $childTab->enabled = 1;
    $childTab->id_parent = $parentTab->id;
    $childTab->position = 1;
    $childTab->name = [1 => 'Child Tab']; // Required for validation
    $childTab->add();

    // Get viewable tabs for SuperAdmin (profile 1)
    $profileId = 1;
    $langId = 1;
    $viewableTabs = $dataProvider->getViewableTabs($profileId, $langId);

    echo "Parent Tab ID: " . $parentTab->id . "\n";
    echo "Parent Tab Active: " . $parentTab->active . "\n";
    echo "Child Tab ID: " . $childTab->id . "\n";
    echo "Child Tab Active: " . $childTab->active . "\n";

    // Before fix: Inactive parent is shown if it has active children.
    // After fix: Inactive parent is NEVER shown (unless it's AdminDashboard).
    if (isset($viewableTabs[$parentTab->id])) {
        echo "BUG: Inactive parent tab is still viewable because it has active children.\n";
        exit(1);
    } else {
        echo "SUCCESS: Inactive parent tab is hidden.\n";
        exit(0);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
