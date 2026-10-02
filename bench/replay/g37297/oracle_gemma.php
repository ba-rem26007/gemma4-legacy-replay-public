<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37297, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Form\Admin\Improve\Shipping\Carrier\Type\CostsZoneType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Mock of FormBuilderInterface to capture the options passed to the 'add' method.
 * Signatures are strictly matched to the Symfony FormBuilderInterface as required by the environment.
 */
class MockFormBuilder implements FormBuilderInterface
{
    public $fields = [];

    public function add($child, ?string $type = null, array $options = []): static
    {
        if (is_string($child)) {
            $this->fields[$child] = $options;
        }
        return $this;
    }

    public function get(string $name): FormBuilderInterface
    {
        return $this;
    }

    public function remove(string $name): void {}
    public function set(string $name, $value): void {}
    public function getName(): string { return 'test_form'; }
    public function getOptions(): array { return []; }
    public function setOptions(array $options): void {}
    public function createView() { return null; }
    public function getData() { return null; }
    public function setData($data): void {}
    public function submit($data, bool $clear = false): void {}
    public function getForm() { return null; }
}

/**
 * We extend CostsZoneType to override the 'trans' method, 
 * as we are running without the Symfony container/translator.
 */
class TestCostsZoneType extends CostsZoneType
{
    public function trans($id, $domain = 'messages', $locale = null)
    {
        return $id;
    }
}

try {
    $builder = new MockFormBuilder();
    $formType = new TestCostsZoneType();
    
    // Call the method touched by the fix
    $formType->buildForm($builder, []);

    if (!isset($builder->fields['ranges'])) {
        echo "Error: 'ranges' field not found in form.\n";
        exit(1);
    }

    $options = $builder->fields['ranges'];
    $allowDelete = isset($options['allow_delete']) && $options['allow_delete'] === true;

    echo "Field 'ranges' allow_delete option: " . ($allowDelete ? 'true' : 'false/missing') . "\n";

    // The bug is the absence of 'allow_delete' => true, which causes an exception 
    // during form submission when a range is removed.
    if ($allowDelete) {
        echo "SUCCESS: allow_delete is enabled.\n";
        exit(0);
    } else {
        echo "FAILURE: allow_delete is missing.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "An unexpected error occurred: " . $t->getMessage() . "\n";
    exit(1);
}
