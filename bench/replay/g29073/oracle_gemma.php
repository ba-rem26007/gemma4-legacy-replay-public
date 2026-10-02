<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29073, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Form\Admin\Product\ProductInformation;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormBuilder;

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);
$context->employee = new Employee(1);

/**
 * Mock classes to satisfy ProductInformation dependencies
 */
class DummyTranslator {
    public function trans($id, $params, $domain) {
        return $id;
    }
}

class DummyRouter {
    public function getLegacyAdminLink($name, $ssl, $params) {
        return 'http://localhost/admin/index.php?controller=' . $name;
    }
}

class DummyDataProvider {
    public function getNestedCategories($root, $lang, $active) {
        return [1 => 'Category 1', 2 => 'Category 2'];
    }
    public function getManufacturers($nb, $lang, $active, $p, $n, $all, $group) {
        return [1 => 'Manufacturer 1'];
    }
}

/**
 * Mock Factory to allow instantiation of FormBuilder
 */
class MockFormFactory implements FormFactoryInterface {
    public function createBuilder($type = null, $name = null, array $options = []) {
        return new MockFormBuilder($this);
    }
    public function create($type = null, $data = null, array $options = []) {
        return null;
    }
    public function createNamed($name, $type = null, $data = null, array $options = []) {
        return null;
    }
}

/**
 * Mock FormBuilder extending the real Symfony FormBuilder to satisfy FormBuilderInterface
 */
class MockFormBuilder extends FormBuilder {
    public $capturedFields = [];

    public function __construct(FormFactoryInterface $factory, ?string $name = null, array $options = []) {
        parent::__construct($factory, $name, $options);
    }

    public function add($name, $type = null, array $options = []) {
        // Capture options instead of executing real Symfony form logic
        $this->capturedFields[$name] = $options;
        return $this;
    }
}

try {
    // Instantiate dependencies
    $translator = new DummyTranslator();
    $router = new DummyRouter();
    $categoryDataProvider = new DummyDataProvider();
    $productDataProvider = new DummyDataProvider();
    $featureDataProvider = new DummyDataProvider();
    $manufacturerDataProvider = new DummyDataProvider();

    // Instantiate the class under test
    $productInformation = new ProductInformation(
        $translator,
        $context,
        $router,
        $categoryDataProvider,
        $productDataProvider,
        $featureDataProvider,
        $manufacturerDataProvider
    );

    // Execute buildForm with our MockFormBuilder
    $factory = new MockFormFactory();
    $builder = new MockFormBuilder($factory);
    $productInformation->buildForm($builder, []);

    // Check the 'id_category' field configuration
    if (!isset($builder->capturedFields['id_category'])) {
        echo "Field 'id_category' not found in form.\n";
        exit(1);
    }

    $options = $builder->capturedFields['id_category'];
    
    // The bug is the presence of 'attr' containing 'data-toggle' => 'select2'
    $hasSelect2Attr = isset($options['attr']['data-toggle']) && $options['attr']['data-toggle'] === 'select2';

    if ($hasSelect2Attr) {
        echo "Bug observed: 'id_category' still has select2 attributes.\n";
        echo "Attr: " . json_encode($options['attr']) . "\n";
        exit(1);
    } else {
        echo "Success: 'id_category' attributes are removed.\n";
        exit(0);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
    exit(1);
}
