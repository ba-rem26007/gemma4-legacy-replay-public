<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #32657, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Form\Admin\Sell\Customer\CustomerType;
use PrestaShopBundle\Form\Admin\Sell\Customer\GroupByIdChoiceProvider;
use PrestaShopBundle\Form\Admin\Sell\Customer\FormCloner;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;

/**
 * Mock FormBuilder to capture the options passed to the 'add' method.
 * Signatures updated to be strictly compatible with Symfony\Component\Form\FormBuilderInterface.
 */
class SimpleFormBuilder implements FormBuilderInterface
{
    public $fields = [];

    public function add(FormBuilderInterface|string $child, ?string $type = null, array $options = []): static
    {
        $this->fields[$child] = $options;
        return $this;
    }

    public function remove(string $name): static
    {
        return $this;
    }

    public function removeByPattern(string $pattern): static
    {
        return $this;
    }

    public function getName(): string
    {
        return '';
    }

    public function setName(string $name): static
    {
        return $this;
    }

    public function getData()
    {
        return null;
    }

    public function setData($data): static
    {
        return $this;
    }

    public function getOptions(): array
    {
        return [];
    }

    public function setOptions(array $options): static
    {
        return $this;
    }

    public function getForm(): FormInterface
    {
        return $this->createMockForm();
    }

    public function createView()
    {
        return null;
    }

    public function getParent()
    {
        return null;
    }

    public function setParent(FormInterface $parent): static
    {
        return $this;
    }

    public function getChildren(): array
    {
        return [];
    }

    public function getExtraOptions(): array
    {
        return [];
    }

    public function setExtraOptions(array $options): static
    {
        return $this;
    }

    private function createMockForm()
    {
        return new class implements FormInterface {
            public function getForm() {}
            public function getParent() {}
            public function getChildren() {}
            public function getName() { return ''; }
            public function remove() {}
            public function getData() {}
            public function setData() {}
            public function getOptions() { return []; }
            public function setOptions() {}
            public function getExtraOptions() { return []; }
            public function setExtraOptions() {}
            public function createView() {}
            public function isValid() { return true; }
            public function isSubmitted() { return true; }
            public function isSynchronizing() { return false; }
        };
    }
}

try {
    // Mock TranslatorInterface
    $translator = new class implements \Symfony\Contracts\Translation\TranslatorInterface {
        public function trans($id, $parameters = [], $domain = null, $locale = null) { return $id; }
        public function getLocale() { return 'fr'; }
        public function setLocale($locale) {}
        public function fallback() {}
    };

    // Mock ConfigurationInterface
    $configuration = new class implements \PrestaShop\PrestaShop\Core\Configuration\ConfigurationInterface {
        public function get($key, $id_shop = null, $id_shop_group = null) { return null; }
        public function set($key, $value, $id_shop = null, $id_shop_group = null) {}
        public function delete($key, $id_shop = null, $id_shop_group = null) {}
        public function updateValue($key, $value, $id_shop = null, $id_shop_group = null) {}
        public function getValues($keys = [], $id_shop = null, $id_shop_group = null) { return []; }
    };

    // Instantiate dependencies using anonymous classes to avoid constructor issues
    $groupProvider = new class extends GroupByIdChoiceProvider {
        public function __construct() {}
    };
    $formCloner = new class extends FormCloner {
        public function __construct() {}
    };

    // Instantiate the Form Type
    $customerType = new CustomerType(
        $translator,
        $groupProvider,
        ['fr' => 'fr'],
        [],
        false,
        false,
        $configuration,
        $formCloner
    );

    // Execute buildForm to populate our mock builder
    $builder = new SimpleFormBuilder();
    $customerType->buildForm($builder, ['is_password_required' => false]);

    // The target field is the default group selection
    $fieldName = 'id_default_group';
    $options = $builder->fields[$fieldName] ?? null;

    if ($options === null) {
        echo "Field $fieldName not found in form configuration.\n";
        exit(1);
    }

    $hasPlaceholderKey = array_key_exists('placeholder', $options);
    $placeholderValue = $options['placeholder'] ?? 'NOT_SET';

    echo "Field: $fieldName\n";
    echo "Has 'placeholder' key: " . ($hasPlaceholderKey ? 'Yes' : 'No') . "\n";
    echo "Placeholder value: " . var_export($placeholderValue, true) . "\n";

    // The fix explicitly adds 'placeholder' => null to remove the empty value in the select2/select box.
    if ($hasPlaceholderKey && $placeholderValue === null) {
        exit(0); // Corrected
    } else {
        exit(1); // Bug still present
    }

} catch (\Throwable $t) {
    echo "Fatal Error: " . $t->getMessage() . "\n";
    exit(1);
}
