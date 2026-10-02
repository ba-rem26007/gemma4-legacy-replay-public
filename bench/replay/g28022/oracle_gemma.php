<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #28022, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Form\Admin\Improve\International\Tax\TaxOptionsType;
use Symfony\Component\Translation\TranslatorInterface;
use PrestaShop\PrestaShop\Core\Form\FormChoiceProviderInterface;

/**
 * The bug is that when disabling tax, the 'display_tax_in_cart' field is not sent in the request.
 * This causes the OptionsResolver in TaxOptionsConfiguration to throw an exception because 
 * the field is defined as required (bool).
 * 
 * The fix is adding 'empty_data' => false to the SwitchType in TaxOptionsType, which ensures 
 * that a value (false) is always submitted even if the switch is not toggled.
 * 
 * To avoid the "60 abstract methods" issue with FormBuilderInterface, we use Reflection 
 * to bypass the type hint of the buildForm method.
 */

// Mock TranslatorInterface
$translator = new class implements TranslatorInterface {
    public function trans($id, $locale = null, $domain = null, $nestedArrays = []) { return $id; }
    public function transChoice($id, $number, $locale = null, $domain = null, $nestedArrays = []) { return $id; }
    public function getLocale() { return 'fr'; }
    public function setLocale($locale) {}
    public function setTranslationDomain($domain) {}
};

// Mock FormChoiceProviderInterface
$choiceProvider = new class implements FormChoiceProviderInterface {
    public function getChoices() { return []; }
};

// Simple mock for the builder that only implements the 'add' method used by TaxOptionsType
$builder = new class {
    public $fields = [];
    public function add($name, $type = null, array $options = []) {
        $this->fields[$name] = $options;
        return $this;
    }
};

try {
    // Instantiate the Form Type
    $formType = new TaxOptionsType(
        $translator,
        ['fr'],
        true,
        $choiceProvider,
        $choiceProvider
    );

    // Use Reflection to call buildForm and bypass the FormBuilderInterface type hint
    $reflection = new ReflectionClass($formType);
    $method = $reflection->getMethod('buildForm');
    $method->setAccessible(true);
    
    // Invoke buildForm with our simple $builder object
    $method->invoke($formType, $builder, []);

    if (!isset($builder->fields['display_tax_in_cart'])) {
        echo "Field 'display_tax_in_cart' not found in the form definition.\n";
        exit(1);
    }

    $options = $builder->fields['display_tax_in_cart'];
    $emptyData = isset($options['empty_data']) ? $options['empty_data'] : 'NOT SET';
    
    echo "Field 'display_tax_in_cart' -> empty_data: " . var_export($emptyData, true) . "\n";

    // The fix is specifically the addition of 'empty_data' => false
    if ($emptyData === false) {
        echo "Correct: 'empty_data' is set to false. This ensures the value is sent even when the switch is off.\n";
        exit(0);
    } else {
        echo "Incorrect: 'empty_data' is not set to false. This will trigger an exception in TaxOptionsConfiguration when the field is empty.\n";
        exit(1);
    }
} catch (\Throwable $t) {
    echo "An unexpected error occurred: " . $t->getMessage() . "\n";
    echo "Trace: " . $t->getTraceAsString() . "\n";
    exit(1);
}
