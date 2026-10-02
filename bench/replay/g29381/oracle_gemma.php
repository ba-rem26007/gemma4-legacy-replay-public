<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29381, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Form\Admin\Sell\Address\CustomerAddressType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Mock for Choice Provider
 */
class MockChoiceProvider
{
    public function getChoices(array $criteria)
    {
        return [];
    }
}

/**
 * Mock for Router
 * We avoid implementing RouterInterface to prevent signature mismatch errors
 */
class MockRouter
{
    public function generate($name, array $parameters = [], $referenceType = null)
    {
        return 'http://mock-url/' . $name;
    }
}

/**
 * Mock for Translator
 */
class MockTranslator
{
    public function trans($id, array $parameters = [], string $domain = null, string $locale = null): string
    {
        return $id;
    }
    public function getLocale(): string
    {
        return 'fr';
    }
    public function setLocale(string $locale): void
    {
    }
}

/**
 * Mock for FormBuilderInterface
 * Must implement the interface to pass the type-hint in buildForm()
 */
class MockBuilder implements FormBuilderInterface
{
    public $options_recorded = [];

    public function add($name, $type = null, array $options = [])
    {
        $this->options_recorded[$name] = $options;
        return $this;
    }

    public function remove($name) {}
    public function removeExtraFields(bool $remove = true) {}
    public function getForm() { return null; }
    public function getData() { return ['id_country' => 1]; }
    public function setData($data) {}
    public function createNormalizedFieldName($name) { return $name; }
    public function getNormalizedFieldName($name) { return $name; }
}

try {
    // Use Reflection to instantiate the class without calling the constructor.
    // This avoids Fatal Errors related to Interface type-hints in the constructor.
    $reflection = new \ReflectionClass(CustomerAddressType::class);
    $formType = $reflection->newInstanceWithoutConstructor();

    // Inject dependencies into private/protected properties
    $props = [
        'translator' => new MockTranslator(),
        'locales' => ['fr'],
        'stateChoiceProvider' => new MockChoiceProvider(),
        'contextCountryId' => 1,
        'router' => new MockRouter(),
    ];

    foreach ($props as $name => $value) {
        if ($reflection->hasProperty($name)) {
            $prop = $reflection->getProperty($name);
            $prop->setAccessible(true);
            $prop->setValue($formType, $value);
        }
    }

    $builder = new MockBuilder();
    $options = [
        'requiredFields' => ['phone_mobile']
    ];

    // Execute the method that defines the form fields
    $formType->buildForm($builder, $options);

    // Check if 'phone_mobile' field was added and if 'empty_data' is set to ''
    $phoneMobileOptions = $builder->options_recorded['phone_mobile'] ?? [];
    $emptyData = $phoneMobileOptions['empty_data'] ?? 'NOT_SET';

    echo "Field 'phone_mobile' empty_data value: " . var_export($emptyData, true) . "\n";

    if ($emptyData === '') {
        echo "SUCCESS: empty_data is correctly set to empty string.\n";
        exit(0);
    } else {
        echo "FAILURE: empty_data is not set to empty string.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "EXCEPTION: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
    exit(1);
}
