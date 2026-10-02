<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #30818, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Controller\Admin\Improve\ModuleController;

try {
    // Setup Context
    $context = Context::getContext();
    $context->shop = new Shop(1);
    $context->language = new Language(1);
    
    // Ensure an employee exists
    $employee = new Employee(1);
    if (!Validate::isLoadedObject($employee)) {
        $e = new Employee();
        $e->firstname = 'Test';
        $e->lastname = 'Employee';
        $e->email = 'test@example.com';
        $e->passwd = 'passwd';
        $e->add();
        $employee = $e;
    }

    if (!class_exists(ModuleController::class)) {
        echo "Error: ModuleController class not found.\n";
        exit(1);
    }

    // We target the manageAction method specifically
    $ref = new ReflectionMethod(ModuleController::class, 'manageAction');
    $docComment = $ref->getDocComment();

    echo "Analyzing @AdminSecurity annotation for ModuleController::manageAction...\n";
    
    if (strpos($docComment, '@AdminSecurity') === false) {
        echo "FAIL: @AdminSecurity annotation not found in docblock.\n";
        exit(1);
    }

    // The bug is the requirement of ALL permissions using '&&'.
    // The fix is to use '||' (OR) or to simplify the requirement.
    // If '&&' is present in the security rule for ADMINMODULESSF_, the bug is still there.
    
    // We check if '&&' is used in conjunction with the module permissions
    if (strpos($docComment, 'ADMINMODULESSF_') !== false && strpos($docComment, '&&') !== false) {
        echo "FAIL: Found '&&' in permissions. All permissions are still required (Bug).\n";
        exit(1);
    }

    // If '||' is present, it's explicitly fixed as per the diff.
    if (strpos($docComment, '||') !== false) {
        echo "SUCCESS: Found '||' in permissions. Any permission is sufficient (Fixed).\n";
        exit(0);
    }

    // If neither '&&' nor '||' is found, but the annotation exists, 
    // it means the restrictive '&&' chain has been removed (e.g., simplified to a single permission),
    // which also resolves the reported bug.
    echo "SUCCESS: Restrictive '&&' permissions not found. Access is no longer blocked by requiring all permissions.\n";
    exit(0);

} catch (\Throwable $t) {
    echo "Fatal Error: " . $t->getMessage() . "\n";
    exit(1);
}
