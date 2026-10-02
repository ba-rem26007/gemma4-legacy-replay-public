<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37985, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * The WidgetInterface is required for the 'instanceof' check in Module::getPossibleHooksList().
 * We use eval() to define the interface in the correct namespace to avoid Fatal Errors in CLI.
 */
eval('namespace PrestaShop\PrestaShop\Core\Framework { 
    interface WidgetInterface { 
        public function renderWidget($hookName, array $configuration); 
    } 
}');

/**
 * Mock module that implements WidgetInterface and has a hook method
 * that is also a "widget" hook (starts with 'display').
 */
class MockWidgetModule extends Module implements \PrestaShop\PrestaShop\Core\Framework\WidgetInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->name = 'mockwidgetmodule';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Test';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
    }

    public function renderWidget($hookName, array $configuration)
    {
        return 'Widget Content';
    }

    // This method makes the module "callable" on the displayHome hook
    public function hookDisplayHome($params)
    {
        return 'Home Content';
    }
}

try {
    // 1. Ensure the module exists in the database
    // We use Db::getInstance()->insert because Module class might be abstract
    $moduleName = 'mockwidgetmodule';
    $id_module = Module::getModuleIdByName($moduleName);
    if (!$id_module) {
        Db::getInstance()->insert('module', [
            'name' => $moduleName,
            'active' => 1,
            'version' => '1.0.0',
        ]);
        $id_module = Db::getInstance()->getValue('SELECT id_module FROM '._DB_PREFIX_.'module WHERE name = "'.pSQL($moduleName).'"');
    }

    // 2. Ensure the 'displayHome' hook exists
    // Hooks starting with 'display' are treated as widget hooks by Hook::getHooks(false, true)
    $hookName = 'displayHome';
    $id_hook = Hook::getIdByName($hookName);
    if (!$id_hook) {
        $h = new Hook();
        $h->name = $hookName;
        $h->title = 'Home';
        $h->description = 'Home hook';
        if (!$h->add()) {
            throw new Exception("Failed to create mock hook in DB");
        }
        $id_hook = $h->id;
    }

    // 3. Instantiate the mock module
    $mockModule = new MockWidgetModule();
    
    // 4. Call the method targeted by the fix
    // This method should return a list of hooks where the module can be hooked.
    $possibleHooks = $mockModule->getPossibleHooksList();

    // 5. Analyze the results for duplicates
    $hookIds = array_column($possibleHooks, 'id_hook');
    $counts = array_count_values($hookIds);
    
    $duplicates = [];
    foreach ($counts as $id => $count) {
        if ($count > 1) {
            $duplicates[] = "Hook ID $id (count: $count)";
        }
    }

    if (!empty($duplicates)) {
        echo "Bug detected: Duplicated hooks found in the transplant list: " . implode(', ', $duplicates) . "\n";
        exit(1);
    }

    echo "Success: No duplicated hooks found. The fix is working.\n";
    exit(0);

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
