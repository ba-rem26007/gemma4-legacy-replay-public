<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29416, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * We define dummy classes in the expected namespaces to satisfy the type-hints 
 * of the CustomerFormCore constructor, as the Symfony autoloader might be 
 * incomplete in this CLI environment.
 */
namespace PrestaShop\PrestaShop\Core\Form\CustomerForm {
    class CustomerFormatter {
        public function __construct($translator) {}
    }
    class CustomerPersister {
        public function __construct($repository) {}
    }
}

namespace PrestaShop\PrestaShop\Core\Domain\Customer {
    class CustomerRepository {
        public function __construct($db) {}
    }
}

namespace {
    use PrestaShop\PrestaShop\Core\Form\CustomerForm\CustomerFormatter;
    use PrestaShop\PrestaShop\Core\Form\CustomerForm\CustomerPersister;
    use PrestaShop\PrestaShop\Core\Domain\Customer\CustomerRepository;

    // 1. Setup Context
    $context = Context::getContext();
    $context->shop = new Shop(1);
    $context->language = new Language(1);
    $context->currency = new Currency(1);

    // Access the translator from Context using Reflection
    $reflector = new ReflectionClass($context);
    $property = $reflector->getProperty('translator');
    $property->setAccessible(true);
    $translator = $property->getValue($context);

    // 2. Create a dummy module to trigger the bug via Hook::exec
    $moduleName = 'test_bug_module';
    $moduleDir = _PS_MODULE_DIR_ . $moduleName;
    if (!is_dir($moduleDir)) {
        mkdir($moduleDir, 0777, true);
    }

    $moduleContent = '<?php
    class Test_Bug_Module extends Module {
        public function __construct() {
            $this->name = "' . $moduleName . '";
            $this->tab = "administration";
            $this->version = "1.0.0";
            $this->author = "test";
            parent::__construct();
        }
        public function hookValidateCustomerFormFields($params) {
            // The bug is triggered when a module returns an array that contains a non-FormField object.
            // Before fix: array_merge adds this array to formFields, then AbstractForm calls isRequired() on it.
            return [
                "extra_field" => [
                    "this_is_an_array_not_an_object" => true
                ]
            ];
        }
    }';
    file_put_contents($moduleDir . '/test_bug_module.php', $moduleContent);

    // Register module in DB
    Db::getInstance()->execute("DELETE FROM ps_module WHERE name = '$moduleName'");
    Db::getInstance()->execute("INSERT INTO ps_module (name, active) VALUES ('$moduleName', 1)");

    // Ensure hook exists and module is attached
    $hookName = 'validateCustomerFormFields';
    $idHook = (int)Db::getInstance()->getValue("SELECT id_hook FROM ps_hook WHERE name = '$hookName'");
    if (!$idHook) {
        Db::getInstance()->execute("INSERT INTO ps_hook (name, title, description, active) VALUES ('$hookName', 'Test Hook', 'Test Hook', 1)");
        $idHook = (int)Db::getInstance()->getValue("SELECT id_hook FROM ps_hook WHERE name = '$hookName'");
    }
    Db::getInstance()->execute("DELETE FROM ps_hook_module WHERE id_hook = $idHook AND id_module = (SELECT id_module FROM ps_module WHERE name = '$moduleName')");
    Db::getInstance()->execute("INSERT INTO ps_hook_module (id_module, id_hook) VALUES ((SELECT id_module FROM ps_module WHERE name = '$moduleName'), $idHook)");

    // 3. Instantiate CustomerForm
    try {
        $smarty = new Smarty();
        
        $formatter = new CustomerFormatter($translator);
        $repository = new CustomerRepository(Db::getInstance());
        $persister = new CustomerPersister($repository);
        
        $urls = [];
        // Use CustomerFormCore directly
        $form = new CustomerFormCore($smarty, $context, $translator, $formatter, $persister, $urls);
        
        // Fill with basic data to avoid early validation failure
        $form->fillWith([
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john@example.com',
            'passwd' => 'Password123!',
        ]);

        echo "Attempting to validate form...\n";
        
        // 4. Trigger the bug
        // Before fix: This will throw "Fatal error: Uncaught Error: Call to a member function isRequired() on array"
        // After fix: This will return false (or true) but will NOT crash.
        $result = $form->validate();
        
        echo "Validation completed without crashing. Result: " . ($result ? 'True' : 'False') . "\n";
        exit(0);

    } catch (\Throwable $t) {
        echo "Caught exception/error: " . $t->getMessage() . "\n";
        if (strpos($t->getMessage(), 'isRequired() on array') !== false) {
            echo "Bug reproduced: Call to isRequired() on array detected.\n";
            exit(1);
        }
        echo "An unexpected error occurred: " . $t->getMessage() . "\n";
        exit(1);
    } finally {
        // Cleanup
        if (is_dir($moduleDir)) {
            array_map('unlink', glob("$moduleDir/*.*"));
            rmdir($moduleDir);
        }
    }
}
