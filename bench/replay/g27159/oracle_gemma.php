<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #27159, validé pre/post automatiquement
require 'config/config.inc.php';

try {
    // Setup Context
    Context::getContext()->shop = new Shop(1);
    Context::getContext()->language = new Language(1);
    Context::getContext()->currency = new Currency(1);

    // 1. Setup Carrier via DB to avoid ObjectModel validation issues
    // We use Carrier 1, ensuring it's configured to trigger the hook
    $id_carrier = 1;
    Db::getInstance()->update('carrier', [
        'shipping_method' => (int)Carrier::SHIPPING_METHOD_WEIGHT,
        'range_behavior' => 1,
        'active' => 1,
    ], 'id_carrier = ' . (int)$id_carrier);

    // Ensure no ranges are defined for this carrier in zone 1 to force the hook call
    Db::getInstance()->delete('delivery', 'id_carrier = ' . (int)$id_carrier . ' AND id_zone = 1');

    // 2. Create a dummy module
    // PrestaShop expects: module name 'testbugmodule' -> file 'testbugmodule.php' -> class 'Testbugmodule'
    $module_name = 'testbugmodule';
    $module_class = 'Testbugmodule';
    $module_dir = _PS_MODULE_DIR_ . $module_name . '/';
    if (!is_dir($module_dir)) {
        mkdir($module_dir, 0777, true);
    }
    
    $module_file_content = '<?php
    class ' . $module_class . ' extends Module {
        public function __construct() {
            $this->name = "' . $module_name . '";
            $this->tab = "shipping_logistics";
            $this->version = "1.0.0";
            $this->author = "Test";
            parent::__construct();
        }
        public function hookActionDeliveryPriceByWeight($params) {
            return 0; // This 0 is the trigger: numeric but falsy
        }
    }';
    file_put_contents($module_dir . $module_name . '.php', $module_file_content);

    // 3. Register module in database via SQL
    Db::getInstance()->delete('module', 'name = "' . $module_name . '"');
    Db::getInstance()->insert('module', [
        'name' => $module_name,
        'active' => 1,
        'version' => '1.0.0',
    ]);
    $id_module = (int)Db::getInstance()->Insert_ID();
    
    $id_hook = (int)Hook::getIdByName('actionDeliveryPriceByWeight');
    if ($id_hook > 0) {
        Db::getInstance()->delete('hook_module', 'id_module = ' . (int)$id_module . ' AND id_hook = ' . (int)$id_hook);
        Db::getInstance()->insert('hook_module', [
            'id_module' => $id_module,
            'id_hook' => $id_hook
        ]);
    } else {
        throw new Exception("Hook actionDeliveryPriceByWeight not found in database.");
    }

    // Clear static caches to ensure the method doesn't use a previous result
    Carrier::resetStaticCache();

    // 4. Execute the code touched by the fix
    // The method checkDeliveryPriceByWeight should:
    // - Find no range in DB
    // - Call Hook::exec('actionDeliveryPriceByWeight')
    // - Receive 0
    // - Before fix: set cache to 0 and return 0 (falsy)
    // - After fix: set cache to true and return true
    $result = Carrier::checkDeliveryPriceByWeight($id_carrier, 1.0, 1);

    echo "Carrier ID: $id_carrier\n";
    echo "Hook actionDeliveryPriceByWeight returns: 0\n";
    echo "Carrier::checkDeliveryPriceByWeight result: " . var_export($result, true) . "\n";

    if ($result === true) {
        echo "SUCCESS: Carrier is correctly marked as available (true) when hook returns 0.\n";
        exit(0);
    } else {
        echo "FAILURE: Carrier is marked as unavailable (0/false) when hook returns 0.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
