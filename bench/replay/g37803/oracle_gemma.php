<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37803, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Define the missing interface to satisfy type-hinting in PreferencesType
 * since the Symfony container/autoloader might not be fully initialized for these bundles in CLI.
 */
namespace PrestaShop\PrestaShopBundle\Config {
    interface ConfigurationInterface {
        public function get($key, $id_shop = null, $id_shop_group = null);
        public function set($key, $value, $id_shop = null, $id_shop_group = null);
        public function delete($key, $id_shop = null, $id_shop_group = null);
        public function updateValue($key, $value, $id_shop = null, $id_shop_group = null);
        public function getValues($key, $id_shop = null, $id_shop_group = null);
    }
}

namespace {
    use PrestaShop\PrestaShopBundle\Form\Admin\Configure\ShopParameters\General\PreferencesType;
    use Symfony\Component\Form\FormBuilderInterface;
    use Symfony\Component\Translation\TranslatorInterface;
    use PrestaShop\PrestaShopBundle\Config\ConfigurationInterface;

    /**
     * Complete implementation of TranslatorInterface.
     */
    class MockTranslator implements TranslatorInterface {
        public function trans($id, array $parameters = [], $domain = null, $locale = null) {
            return $id;
        }
        public function translate($id, array $parameters = [], $domain = null, $locale = null) {
            return $id;
        }
        public function transChoice($id, $number, array $parameters = [], $domain = null, $locale = null) {
            return $id;
        }
        public function getLocale() {
            return 'fr';
        }
        public function setLocale($locale) {}
        public function setFallbackLocales(array $locales) {}
        public function getFallbackLocales() {
            return [];
        }
    }

    /**
     * Implementation of ConfigurationInterface.
     */
    class MockConfiguration implements ConfigurationInterface {
        public function get($key, $id_shop = null, $id_shop_group = null) {
            return 'mock_value';
        }
        public function set($key, $value, $id_shop = null, $id_shop_group = null) {}
        public function delete($key, $id_shop = null, $id_shop_group = null) {}
        public function updateValue($key, $value, $id_shop = null, $id_shop_group = null) {}
        public function getValues($key, $id_shop = null, $id_shop_group = null) {
            return [];
        }
    }

    /**
     * Implementation of FormBuilderInterface.
     */
    class MockFormBuilder implements FormBuilderInterface {
        public $fields = [];
        public function add($name, $type = null, array $options = []) {
            $this->fields[$name] = $options;
        }
        public function remove($name) {}
        public function getForm() { return null; }
        public function createBlock($name, $type = null, array $options = []) {}
        public function getOption($name) { return null; }
        public function setOption($name, $value) {}
        public function getOptions() { return []; }
        public function getRoot() { return null; }
        public function getName() { return ''; }
        public function getParent() { return null; }
        public function setParent($parent) {}
    }

    try {
        $translator = new MockTranslator();
        $config = new MockConfiguration();
        $locales = [];

        // Test Case 1: Multistore is used (isMultistoreUsed = true)
        // Expected after fix: 'multishop_feature_active' field should be DISABLED (disabled => true)
        $builder1 = new MockFormBuilder();
        $type1 = new PreferencesType($translator, $locales, $config, true, true, true);
        $type1->buildForm($builder1, []);
        $disabledWhenUsed = $builder1->fields['multishop_feature_active']['disabled'] ?? null;

        // Test Case 2: Multistore is NOT used (isMultistoreUsed = false)
        // Expected after fix: 'multishop_feature_active' field should NOT be disabled (disabled => false)
        $builder2 = new MockFormBuilder();
        $type2 = new PreferencesType($translator, $locales, $config, false, true, true);
        $type2->buildForm($builder2, []);
        $disabledWhenNotUsed = $builder2->fields['multishop_feature_active']['disabled'] ?? null;

        echo "Multistore used -> disabled: " . ($disabledWhenUsed ? 'true' : 'false') . "\n";
        echo "Multistore not used -> disabled: " . ($disabledWhenNotUsed ? 'true' : 'false') . "\n";

        // The fix changes the logic to: 'disabled' => $this->isMultistoreUsed
        if ($disabledWhenUsed === true && $disabledWhenNotUsed === false) {
            exit(0);
        } else {
            echo "Logic mismatch: expected disabled=true when used and disabled=false when not used.\n";
            exit(1);
        }
    } catch (\Throwable $t) {
        echo "Fatal Error: " . $t->getMessage() . "\n";
        exit(1);
    }
}
