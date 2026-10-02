<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #32506, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Form\Admin\Configure\AdvancedParameters\Import\ImportType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Mock FormBuilder to capture the options passed to the 'add' method.
 * Must implement FormBuilderInterface and IteratorAggregate (since FormBuilderInterface extends Traversable).
 */
class MockFormBuilder implements FormBuilderInterface, \IteratorAggregate
{
    public $fields = [];

    public function add(FormBuilderInterface|string $child, ?string $type = null, array $options = []): static
    {
        $name = is_string($child) ? $child : 'unknown';
        $this->fields[$name] = $options;
        return $this;
    }

    public function remove(string $name): static { return $this; }
    public function get(string $name): FormInterface { throw new \Exception("Not implemented"); }
    public function getForm(): FormInterface { throw new \Exception("Not implemented"); }
    public function setData($data): static { return $this; }
    public function getData() { return null; }
    public function configureOptions(OptionsResolver $resolver): static { return $this; }
    public function getOptions(): array { return []; }
    public function setExtras(array $extras): static { return $this; }
    public function getExtras(): array { return []; }
    public function getIterator(): \Traversable { return new \ArrayIterator($this->fields); }
}

/**
 * We create a testable version of ImportType to override the 'trans' method.
 * The signature must match TranslatorAwareType::trans($key, $domain, $parameters = [])
 */
class TestImportType extends ImportType
{
    public function trans($key, $domain, $parameters = [])
    {
        return $key;
    }
}

try {
    // Initialize Context
    Context::getContext()->shop = new Shop(1);
    Context::getContext()->language = new Language(1);

    // Instantiate the Mock Builder and our TestImportType
    $builder = new MockFormBuilder();
    $importType = new TestImportType();

    // Execute the buildForm method
    $importType->buildForm($builder, []);

    // Retrieve the options configured for the 'iso_lang' field
    if (!isset($builder->fields['iso_lang'])) {
        echo "Field 'iso_lang' not found in form.\n";
        exit(1);
    }

    $options = $builder->fields['iso_lang'];
    
    // Check if 'placeholder' key exists and is explicitly set to null
    $hasPlaceholderKey = array_key_exists('placeholder', $options);
    $placeholderValue = isset($options['placeholder']) ? $options['placeholder'] : 'NOT SET';

    echo "iso_lang placeholder exists: " . ($hasPlaceholderKey ? 'Yes' : 'No') . "\n";
    echo "iso_lang placeholder value: " . var_export($placeholderValue, true) . "\n";

    /**
     * The bug: The 'iso_lang' field did not have 'placeholder' => null.
     * The fix: Explicitly adding 'placeholder' => null in ImportType.php.
     */
    if ($hasPlaceholderKey && $options['placeholder'] === null) {
        echo "Success: Placeholder is explicitly set to null.\n";
        exit(0);
    } else {
        echo "Failure: Placeholder is not explicitly null.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Fatal Error: " . $t->getMessage() . "\n";
    exit(1);
}
