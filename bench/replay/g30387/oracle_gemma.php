<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #30387, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Subclass of HelperList to access protected properties and bypass template loading
 */
class TestHelperList extends HelperList
{
    public function setList($list)
    {
        $this->_list = $list;
    }

    public function setFields($fields)
    {
        $this->fields_list = $fields;
    }

    public function getList()
    {
        return $this->_list;
    }

    // Override createTemplate to prevent "Unable to load template" errors in CLI
    public function createTemplate($tpl)
    {
        return new class {
            public function assign($params) { return $this; }
            public function fetch() { return ''; }
        };
    }
}

/**
 * Dummy class to handle the HelperList callback
 */
class HelperListCallbackHandler
{
    public function myCustomCallback($data, $row)
    {
        return 'CALLBACK_EXECUTED';
    }
}

// Setup context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->controller = new stdClass();

// 1. Prepare data: a list where one column value is explicitly NULL
$list = [
    [
        'id' => 1,
        'test_column' => null,
    ],
    [
        'id' => 2,
        'test_column' => 'some value',
    ],
];

// 2. Define fields for HelperList
$callbackHandler = new HelperListCallbackHandler();
$fields_display = [
    'id' => [
        'title' => 'ID',
        'width' => 'auto',
    ],
    'test_column' => [
        'title' => 'Test Column',
        'callback' => 'myCustomCallback',
        'callback_object' => $callbackHandler,
        'width' => 'auto',
    ],
];

try {
    $helper = new TestHelperList();
    $helper->setList($list);
    $helper->setFields($fields_display);
    $helper->context = $context;

    // Set required properties to avoid "Missing parameter" exceptions when generating links
    $helper->currentIndex = 'index.php';
    $helper->token = 'test_token';
    $helper->identifier = 'test_id';

    // displayListContent() is the method that iterates over the list 
    // and applies the callbacks, modifying $this->_list in place.
    $helper->displayListContent();

    $resultList = $helper->getList();
    $observedValueNull = $resultList[0]['test_column'];
    $observedValueNotNull = $resultList[1]['test_column'];

    echo "Value for NULL entry: " . var_export($observedValueNull, true) . "\n";
    echo "Value for non-NULL entry: " . var_export($observedValueNotNull, true) . "\n";

    // The test passes if the callback was executed even for the NULL value
    if ($observedValueNull === 'CALLBACK_EXECUTED' && $observedValueNotNull === 'CALLBACK_EXECUTED') {
        exit(0);
    } else {
        echo "Error: Callback was not called for the NULL value.\n";
        exit(1);
    }
} catch (\Throwable $t) {
    echo "Exception: " . $t->getMessage() . "\n";
    exit(1);
}
