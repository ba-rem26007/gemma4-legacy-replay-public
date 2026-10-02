<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #28094, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Dummy module that implements WidgetInterface to trigger the bug.
 * The bug is that WidgetInterface modules were restricted in which hooks 
 * they could be transplanted to and which hooks were considered "hookable".
 */
class TestWidgetModule extends Module implements \PrestaShop\PrestaShop\Core\Module\WidgetInterface
{
    public function __construct()
    {
        // Signature: __construct($name = null, Context $context = null)
        parent::__construct('testwidgetmodule', Context::getContext());
        $this->displayName = 'Test Widget Module';
        $this->version = '1.0.0';
        $this->author = 'Test';
    }

    /**
     * Required by WidgetInterface.
     */
    public function getWidgetHooks()
    {
        return [];
    }

    /**
     * Required by WidgetInterface.
     */
    public function renderWidget($hookName, array $configuration)
    {
        return 'Widget Content';
    }

    /**
     * Required by WidgetInterface.
     */
    public function getWidgetVariables($hookName, array $configuration)
    {
        return [];
    }

    /**
     * Standard display hook.
     */
    public function hookDisplayHeader()
    {
        return 'Header Content';
    }

    /**
     * Standard action hook (non-display).
     */
    public function hookActionProductUpdate($params)
    {
        return true;
    }
}

try {
    $module = new TestWidgetModule();

    // Test 1: isHookableOn for a non-display hook
    // BEFORE: If WidgetInterface, it only returned Hook::isDisplayHookName($hook_name).
    // Since 'actionProductUpdate' is not a display hook, it returned false.
    // AFTER: It falls back to is_callable([$this, 'hookActionProductUpdate']).
    $isHookableAction = $module->isHookableOn('actionProductUpdate');

    // Test 2: getPossibleHooksList for a display hook not in getWidgetHooks()
    // BEFORE: If WidgetInterface, it returned ONLY getWidgetHooks().
    // Since we returned [], 'displayHeader' was missing from the list.
    // AFTER: It merges getWidgetHooks() with the general list of hooks.
    $possibleHooks = $module->getPossibleHooksList();
    $hookNames = array_column($possibleHooks, 'name');
    $hasDisplayHeader = in_array('displayHeader', $hookNames);

    echo "isHookableOn('actionProductUpdate'): " . ($isHookableAction ? 'TRUE' : 'FALSE') . "\n";
    echo "displayHeader in getPossibleHooksList: " . ($hasDisplayHeader ? 'TRUE' : 'FALSE') . "\n";

    if ($isHookableAction && $hasDisplayHeader) {
        exit(0);
    } else {
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
