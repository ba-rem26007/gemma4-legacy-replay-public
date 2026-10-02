<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #32465, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Mock Smarty class to avoid template loading errors in CLI
 * and capture variables assigned to the KPI templates.
 */
class MockSmarty {
    public $vars = [];
    public function assign($data, $name = null) {
        if ($name === null) {
            if (is_array($data)) {
                $this->vars = array_merge($this->vars, $data);
            }
        } else {
            $this->vars[$name] = $data;
        }
    }
    public function fetch($template, $compile_id = false) {
        // We return the current variables as a string to be parsed by the test
        return 'SMARTY_VARS:' . json_encode($this->vars);
    }
    public function __call($name, $args) {
        return null;
    }
}

// Setup context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->employee = new Employee(1);

// Inject the mock Smarty into the global context singleton
$mockSmarty = new MockSmarty();
$context->smarty = $mockSmarty;

try {
    // Instantiate the controller
    $controller = new AdminCustomerThreadsController();
    
    // Ensure the controller is using the same context with our mock Smarty
    $controller->context = $context;

    // Call the method that generates the KPIs
    $kpis = $controller->renderKpis();
    
    if (!is_array($kpis) || empty($kpis)) {
        echo "Error: renderKpis() did not return an array of KPIs.\n";
        exit(1);
    }

    // The first KPI is 'box-pending-messages'
    $firstKpiOutput = $kpis[0];
    
    // Extract variables from our mock output
    if (strpos($firstKpiOutput, 'SMARTY_VARS:') !== 0) {
        echo "Error: Unexpected output format from mock Smarty: " . $firstKpiOutput . "\n";
        exit(1);
    }

    $varsJson = substr($firstKpiOutput, 12);
    $vars = json_decode($varsJson, true);

    echo "KPI Variables: " . $varsJson . "\n";

    // The bug: $helper->href was set to the link of AdminCustomerThreads.
    // After the fix, 'href' should be empty or not set for the first KPI.
    if (!empty($vars['href'])) {
        echo "FAIL: The KPI still contains a link in 'href': " . $vars['href'] . "\n";
        exit(1);
    } else {
        echo "SUCCESS: The useless redirection link (href) has been removed.\n";
        exit(0);
    }

} catch (\Throwable $t) {
    echo "Exception caught: " . $t->getMessage() . "\n";
    exit(1);
}
