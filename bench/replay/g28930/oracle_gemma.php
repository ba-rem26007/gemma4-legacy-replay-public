<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #28930, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Helper class to access protected properties of AdminController
 */
class TestAdminController extends AdminController
{
    public function setTable($table) { $this->table = $table; }
    public function setClassName($className) { $this->className = $className; }
    public function setMultishopContext($val) { $this->multishop_context = $val; }
    public function setJoin($join) { $this->_join = $join; }
    public function setFieldsList($list) { $this->fields_list = $list; }
    public function getListResults() { return $this->_list; }
}

try {
    // 1. Setup Multistore
    Configuration::updateValue('PS_MULTISHOP_ACTIVE', 1);
    
    $shop1Id = 1;
    
    // Create Shop 2
    $shop2 = new Shop();
    $shop2->id_country = 1;
    $shop2->id_shop_group = 1;
    $shop2->id_category = 2; // Required field
    $shop2->name = 'Shop 2';
    $shop2->active = 1;
    $shop2->add();
    $shop2Id = $shop2->id;

    // 2. Create a Feature associated ONLY with Shop 1
    $feature = new Feature();
    $feature->name = [1 => 'Bug Test Feature'];
    $feature->add();
    $featureId = $feature->id;

    // Manually associate with Shop 1 in the feature_shop table
    Db::getInstance()->execute('INSERT INTO ' . _DB_PREFIX_ . 'feature_shop (id_feature, id_shop) VALUES (' . (int)$featureId . ', ' . (int)$shop1Id . ')');

    // 3. Set global context to Shop 2 BEFORE instantiating the controller
    Shop::setContext(Shop::CONTEXT_SHOP, $shop2Id);
    $context = Context::getContext();
    $context->shop = new Shop($shop2Id);
    $context->language = new Language(1);
    
    // Ensure employee is loaded (Employee 1 is usually superadmin, but Shop::CONTEXT_SHOP triggers the filter)
    $employee = new Employee(1);
    $context->employee = $employee;

    // 4. Instantiate the helper controller
    $ctrl = new TestAdminController();
    $ctrl->setTable('feature');
    $ctrl->setClassName('Feature');
    $ctrl->setMultishopContext(true);
    
    // The bug is triggered when _join is null.
    // Before fix: if _join is null, the condition `null !== $this->_join` is false, 
    // and the shop filtering (EXISTS clause) is skipped.
    $ctrl->setJoin(null); 
    
    $ctrl->setFieldsList([
        'id_feature' => ['title' => 'ID']
    ]);

    // Clear globals to avoid filter interference
    $_GET = [];
    $_POST = [];

    // 5. Execute getList
    $ctrl->getList(1);
    $list = $ctrl->getListResults();

    // 6. Check if the feature from Shop 1 appears in Shop 2's list
    $found = false;
    if (!empty($list)) {
        foreach ($list as $row) {
            if (isset($row['id_feature']) && (int)$row['id_feature'] === $featureId) {
                $found = true;
                break;
            }
        }
    }

    echo "Shop Context: $shop2Id\n";
    echo "Feature ID: $featureId (associated with Shop $shop1Id)\n";
    echo "Feature found in list: " . ($found ? 'YES' : 'NO') . "\n";

    if ($found) {
        echo "BUG: Feature associated with Shop 1 is visible in Shop 2.\n";
        exit(1);
    } else {
        echo "SUCCESS: Feature is correctly filtered by shop.\n";
        exit(0);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
