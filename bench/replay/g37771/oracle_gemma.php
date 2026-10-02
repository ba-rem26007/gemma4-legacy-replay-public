<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37771, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Form\Admin\Sell\Product\Description\DescriptionType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * Mock classes to satisfy Symfony interface requirements without the container
 */
class MockTranslator implements \Symfony\Contracts\Translation\TranslatorInterface {
    public function trans($id, $parameters = [], $domain = null, $locale = null) { return $id; }
    public function getLocale() { return 'fr'; }
    public function setLocale($locale) {}
}

class MockRouter implements \Symfony\Component\Routing\RouterInterface {
    public function generate($name, $parameters = [], $exact = false) { return ''; }
    public function match($path) { return []; }
    public function getRouteCollection() { return new \Symfony\Component\Routing\RouteCollection(); }
    public function setContext(\Symfony\Component\Routing\RequestContext $context) {}
    public function getContext() { return new \Symfony\Component\Routing\RequestContext(); }
}

class MockFormFactory implements FormFactoryInterface {
    public function createBuilder($type = null, $name = null, array $options = []) {
        return new MockFormBuilder($this);
    }
    public function create($type = null, $options = []) { return null; }
    public function createNamed($name, $type = null, array $options = []) { return null; }
}

class MockFormBuilder extends FormBuilder {
    public $calls = [];
    public function add($name, $type = null, array $options = []) {
        // We record the call and return $this without calling parent::add
        // to avoid triggering Symfony's internal form resolution logic.
        $this->calls[$name] = ['type' => $type, 'options' => $options];
        return $this;
    }
}

try {
    // Setup context
    Context::getContext()->shop = new Shop(1);
    Context::getContext()->language = new Language(1);

    // Instantiate dependencies
    $translator = new MockTranslator();
    $router = new MockRouter();
    $factory = new MockFormFactory();
    $builder = new MockFormBuilder($factory);

    // Instantiate the class under test
    // Constructor: TranslatorInterface, array $locales, RouterInterface, string $employeeIsoCode, int $shortDescriptionMaxLength
    $descriptionType = new DescriptionType(
        $translator,
        ['fr'],
        $router,
        'test_emp',
        2000
    );

    // Execute the method that builds the form
    $descriptionType->buildForm($builder, [
        'product_id' => 1,
        'shop_id' => 1
    ]);

    // The bug is the presence of a 'Length' constraint in the 'description' field options
    if (!isset($builder->calls['description'])) {
        echo "Error: 'description' field not found in form builder calls.\n";
        exit(1);
    }

    $fieldOptions = $builder->calls['description']['options'];
    $innerOptions = $fieldOptions['options'] ?? [];
    $constraints = $innerOptions['constraints'] ?? [];

    $hasLengthConstraint = false;
    foreach ($constraints as $constraint) {
        if ($constraint instanceof Length) {
            $hasLengthConstraint = true;
            break;
        }
    }

    if ($hasLengthConstraint) {
        echo "Bug observed: Length constraint is still present in DescriptionType.\n";
        exit(1);
    } else {
        echo "Success: Length constraint has been removed.\n";
        exit(0);
    }

} catch (\Throwable $t) {
    echo "Fatal error during test: " . $t->getMessage() . "\n";
    exit(1);
}
