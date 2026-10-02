<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #32589, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Form\Admin\Sell\Customer\GroupType;
use PrestaShop\PrestaShop\Adapter\Form\ChoiceProvider\GroupByIdChoiceProvider;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * MockResolver allows us to capture the array passed to setDefaults()
 * without triggering the full Symfony OptionsResolver validation logic.
 */
class MockResolver extends OptionsResolver
{
    public $capturedDefaults = [];

    public function setDefaults(array $defaults)
    {
        $this->capturedDefaults = $defaults;
        // We avoid calling parent::setDefaults to prevent validation errors 
        // since we only care about the keys being passed.
    }
}

try {
    // 1. Instantiate GroupType without constructor to avoid dependency injection
    $reflectionGroupType = new ReflectionClass(GroupType::class);
    $groupType = $reflectionGroupType->newInstanceWithoutConstructor();

    // 2. Instantiate GroupByIdChoiceProvider without constructor (it's a final class)
    $reflectionProvider = new ReflectionClass(GroupByIdChoiceProvider::class);
    $provider = $reflectionProvider->newInstanceWithoutConstructor();

    // 3. To prevent getChoices() from crashing due to missing internal dependencies 
    // (like a repository), we inject a dummy object into all its properties.
    $dummyRepo = new class {
        public function getGroups() { return []; }
        public function findAll() { return []; }
        public function __call($name, $args) { return []; }
    };

    foreach ($reflectionProvider->getProperties() as $prop) {
        $prop->setAccessible(true);
        try {
            $prop->setValue($provider, $dummyRepo);
        } catch (\TypeError $e) {
            // Ignore properties that cannot accept the dummy object
        }
    }

    // 4. Inject the provider into GroupType
    $property = $reflectionGroupType->getProperty('groupByIdChoiceProvider');
    $property->setAccessible(true);
    $property->setValue($groupType, $provider);

    // 5. Use the MockResolver to capture the defaults
    $mockResolver = new MockResolver();
    $groupType->configureOptions($mockResolver);
    $defaults = $mockResolver->capturedDefaults;

    $hasPlaceholder = array_key_exists('placeholder', $defaults);
    $hasAutocomplete = array_key_exists('autocomplete', $defaults);

    echo "Placeholder key exists in defaults: " . ($hasPlaceholder ? 'Yes' : 'No') . "\n";
    if ($hasPlaceholder) {
        echo "Placeholder value: " . var_export($defaults['placeholder'], true) . "\n";
    }
    
    echo "Autocomplete key exists in defaults: " . ($hasAutocomplete ? 'Yes' : 'No') . "\n";
    if ($hasAutocomplete) {
        echo "Autocomplete value: " . var_export($defaults['autocomplete'], true) . "\n";
    }

    // The fix removes both 'placeholder' => null and 'autocomplete' => true.
    if ($hasPlaceholder || $hasAutocomplete) {
        echo "Regression detected: GroupType still defines default placeholder or autocomplete.\n";
        exit(1);
    }

    echo "Success: GroupType defaults are cleaned up.\n";
    exit(0);

} catch (\Throwable $t) {
    echo "Fatal error during test execution: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
    exit(1);
}
