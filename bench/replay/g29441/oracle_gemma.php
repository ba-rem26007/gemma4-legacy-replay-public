<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29441, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);
$employee = new Employee(1);
Context::getContext()->employee = $employee;

/**
 * Wrapper for AdminController to access protected l() method
 */
class TestAdminController extends AdminController
{
    public function publicL($string, $class = null, $addslashes = false, $htmlentities = true)
    {
        return $this->l($string, $class, $addslashes, $htmlentities);
    }
}

/**
 * Wrapper for ModuleAdminController to access protected l() method
 * and bypass the constructor's Tab requirement.
 */
class TestModuleAdminController extends ModuleAdminController
{
    public function __construct()
    {
        // Bypass parent::__construct() to avoid Tab/Database requirements
        $this->controller_type = 'moduleadmin';
        $this->module = new Module();
        $this->module->name = 'testmodule';
    }

    public function publicL($string, $class = null, $addslashes = false, $htmlentities = true)
    {
        return $this->l($string, $class, $addslashes, $htmlentities);
    }
}

try {
    $testString = 'Translation "with quotes"';
    $expected = 'Translation &quot;with quotes&quot;';

    // Get the translator from Context using Reflection because it is protected
    $context = Context::getContext();
    $refContext = new ReflectionClass('Context');
    $propContext = $refContext->getProperty('translator');
    $propContext->setAccessible(true);
    $translator = $propContext->getValue($context);

    // 1. Test AdminController
    $adminCtrl = new TestAdminController();
    // Set the protected translator property using Reflection
    $refAdmin = new ReflectionClass('AdminController');
    $propAdmin = $refAdmin->getProperty('translator');
    $propAdmin->setAccessible(true);
    $propAdmin->setValue($adminCtrl, $translator);
    
    $resAdmin = $adminCtrl->publicL($testString, null, false, true);
    echo "AdminController result: $resAdmin\n";

    // 2. Test ModuleAdminController
    $modAdminCtrl = new TestModuleAdminController();
    // Set the protected translator property using Reflection
    $propAdmin->setValue($modAdminCtrl, $translator);
    
    $resMod = $modAdminCtrl->publicL($testString, null, false, true);
    echo "ModuleAdminController result: $resMod\n";

    if ($resAdmin === $expected && $resMod === $expected) {
        echo "SUCCESS: Special characters are properly escaped.\n";
        exit(0);
    } else {
        echo "FAILURE: Special characters are not escaped.\n";
        echo "Expected: $expected\n";
        echo "Admin got: $resAdmin\n";
        echo "Module got: $resMod\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
