<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #36403, validé pre/post automatiquement
require 'config/config.inc.php';

use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Form\FormBuilderInterface;
use PrestaShop\PrestaShop\Core\Feature\FeatureInterface;
use PrestaShop\PrestaShop\Core\Configuration\ConfigurationInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Mock Translator to return the translation keys as they are, 
 * allowing us to detect the duplication in the help text.
 */
class MockTranslator implements TranslatorInterface {
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string {
        $text = $id;
        foreach ($parameters as $key => $value) {
            $text = str_replace('%s', (string)$value, $text);
        }
        return $text;
    }
    public function getLocale(): string { return 'fr'; }
}

/**
 * Mock FormBuilder to capture the options passed to the 'add' method.
 */
class MockFormBuilder implements FormBuilderInterface {
    public $fields = [];
    public function add($child, ?string $type = null, array $options = []): static {
        $this->fields[$child] = $options;
        return $this;
    }
    public function remove($name) {}
    public function get($name) {}
    public function set($name, $form) {}
    public function getName() { return ''; }
    public function getOptions() { return []; }
    public function setOptions(array $options) {}
    public function getForm() { return null; }
}

// Mock dependencies for the constructor
$translator = new MockTranslator();
$locales = [1 => 'fr'];
$customerGroupChoices = [];

$feature = new class implements FeatureInterface {
    public function getEnabledFeatures() { return []; }
};

$configuration = new class implements ConfigurationInterface {
    public function get($key, $id_shop = null, $id_shop_group = null) { return null; }
    public function set($key, $value, $id_shop = null, $id_shop_group = null) {}
    public function updateValue($key, $value, $id_shop = null, $id_shop_group = null) {}
    public function deleteValue($key, $id_shop = null, $id_shop_group = null) {}
    public function getValues($key, $id_shop = null, $id_shop_group = null) { return []; }
};

$router = new class implements UrlGeneratorInterface {
    public function generate($name, array $parameters = [], int $referenceType = 0) { return ''; }
    public function generateAbsoluteUrl($name, array $parameters = [], int $referenceType = 0) { return ''; }
};

try {
    // Use an anonymous class to instantiate the abstract AbstractCategoryType
    $type = new class($translator, $locales, $customerGroupChoices, $feature, $configuration, $router) 
        extends \PrestaShopBundle\Form\Admin\Catalog\Category\AbstractCategoryType {
    };

    $builder = new MockFormBuilder();
    $type->buildForm($builder, []);

    if (!isset($builder->fields['meta_keyword'])) {
        echo "Error: Field 'meta_keyword' was not added to the form.\n";
        exit(1);
    }

    $helpText = $builder->fields['meta_keyword']['help'] ?? '';
    echo "Observed help text: $helpText\n";

    // The bug is that "Invalid characters" appears twice: 
    // once in the hardcoded translation and once in $genericCharactersHint.
    $count = substr_count($helpText, 'Invalid characters');
    echo "Occurrences of 'Invalid characters': $count\n";

    // If count is 1, the duplication is fixed. If 2, the bug is present.
    exit($count === 1 ? 0 : 1);

} catch (\Throwable $t) {
    echo "Fatal error: " . $t->getMessage() . "\n";
    exit(1);
}
