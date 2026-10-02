<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #31148, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Form\Admin\Product\ProductOptions;
use Symfony\Component\Form\FormBuilder;

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

/**
 * FakeBuilder extends the real FormBuilder to satisfy the FormBuilderInterface 
 * type hint without needing a full Symfony FormFactory setup.
 */
class FakeBuilder extends FormBuilder {
    public $capturedChoices = [];

    // Override constructor to avoid needing FormFactoryInterface
    public function __construct() {}

    // Override add to capture the choices for the 'condition' field
    public function add($name, $type = null, $options = []) {
        if ($name === 'condition') {
            $this->capturedChoices = $options['choices'] ?? [];
        }
        return $this;
    }

    // Override create to avoid calling the factory
    public function create($name, $type = null, $options = []) {
        return 'dummy_form_element';
    }
}

/**
 * Mock Translator to capture the domain used for translation.
 * Instead of translating, it returns the domain name.
 */
$mockTranslator = new class {
    public function trans($id, array $parameters = [], $domain = null) {
        return $domain;
    }
};

try {
    // Instantiate ProductOptions without calling the constructor to avoid dependency hell
    $reflector = new ReflectionClass(ProductOptions::class);
    $productOptions = $reflector->newInstanceWithoutConstructor();

    // Inject the mock translator into the private/protected property
    $translatorProp = $reflector->getProperty('translator');
    $translatorProp->setAccessible(true);
    $translatorProp->setValue($productOptions, $mockTranslator);

    // Use our FakeBuilder which implements FormBuilderInterface
    $builder = new FakeBuilder();

    // Execute the method that contains the bug/fix
    $productOptions->buildForm($builder, []);

    // The 'condition' choices are [ 'Translated Label' => 'value' ]
    // Our mock translator returns the domain as the label.
    $choices = $builder->capturedChoices;
    $domainUsedForNew = '';
    foreach ($choices as $label => $value) {
        if ($value === 'new') {
            $domainUsedForNew = $label;
            break;
        }
    }

    echo "Domain used for 'New' in BO: $domainUsedForNew\n";

    // Expected: 'Admin.Global'
    // Bug: 'Shop.Theme.Catalog'
    if ($domainUsedForNew === 'Admin.Global') {
        exit(0);
    } else {
        echo "Error: Expected domain 'Admin.Global', but got '$domainUsedForNew'\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Exception: " . $t->getMessage() . "\n";
    echo "File: " . $t->getFile() . " line " . $t->getLine() . "\n";
    exit(1);
}
