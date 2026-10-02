<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #28447, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Setup Context
 */
$context = Context::getContext();
$context->employee = new Employee(1);
$context->language = new Language(1);
$context->shop = new Shop(1);

/**
 * We extend AdminController to trigger the toolbar initialization.
 * The method mergeExtraToolbarButtons() is called inside initPageHeaderToolbar().
 * The resulting collection is typically assigned to Smarty.
 */
class TestAdminController extends AdminController {
    public function triggerToolbar() {
        // Scenario: Button missing the 'class' key. 
        // The index (0) should be used as the identifier after the fix.
        $this->page_header_toolbar_btn = [
            0 => [
                'href' => 'http://test.com',
                'desc' => 'Test Button'
            ]
        ];
        
        // This method calls mergeExtraToolbarButtons() and assigns the result to Smarty
        $this->initPageHeaderToolbar();
    }
}

try {
    $controller = new TestAdminController();
    $controller->triggerToolbar();

    // The result of mergeExtraToolbarButtons is stored in the Smarty template variables
    $smartyVars = $context->smarty->getTemplateVars();
    $foundButton = null;

    // We look for the ActionsBarButton objects in all Smarty variables
    foreach ($smartyVars as $var) {
        if (is_array($var) || $var instanceof Traversable) {
            foreach ($var as $item) {
                if ($item instanceof ActionsBarButton) {
                    $foundButton = $item;
                    break 2;
                }
                // Check if it's a collection containing ActionsBarButton
                if (is_array($item) || $item instanceof Traversable) {
                    foreach ($item as $subItem) {
                        if ($subItem instanceof ActionsBarButton) {
                            $foundButton = $subItem;
                            break 3;
                        }
                    }
                }
            }
        }
    }

    if (!$foundButton) {
        echo "Error: No ActionsBarButton found in Smarty variables after initPageHeaderToolbar().\n";
        exit(1);
    }

    // Use Reflection to inspect the identifier passed to the ActionsBarButton constructor.
    $reflector = new ReflectionClass($foundButton);
    $idValue = null;

    // We check common property names for the identifier.
    $propertiesToTry = ['id', 'identifier', 'class', 'buttonId'];
    foreach ($propertiesToTry as $propName) {
        if ($reflector->hasProperty($propName)) {
            $prop = $reflector->getProperty($propName);
            $prop->setAccessible(true);
            $val = $prop->getValue($foundButton);
            if ($val !== null && $val !== '') {
                $idValue = $val;
                break;
            }
        }
    }

    // Fallback: search all properties for the value 0 or '0' (the expected index)
    if ($idValue === null || $idValue === '') {
        foreach ($reflector->getProperties() as $prop) {
            $prop->setAccessible(true);
            $val = $prop->getValue($foundButton);
            if ($val === 0 || $val === '0') {
                $idValue = $val;
                break;
            }
        }
    }

    echo "Observed Button ID: " . var_export($idValue, true) . "\n";

    /**
     * BEFORE FIX: $controllerButton['class'] ?? '' => result is ''
     * AFTER FIX: $controllerButton['class'] ?? $controllerButtonIndex => result is 0
     */
    if ($idValue === 0 || $idValue === '0') {
        echo "Success: Button ID is the array index (0).\n";
        exit(0);
    } else {
        echo "Failure: Button ID is empty or incorrect. Expected 0, got " . var_export($idValue, true) . "\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Fatal Error: " . $t->getMessage() . "\n";
    exit(1);
}
