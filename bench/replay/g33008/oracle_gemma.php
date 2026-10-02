<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33008, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Core\Form\ChoiceProvider\ProductConditionChoiceProvider;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductCondition;

/**
 * Mock Translator to verify the translation domain used.
 * Implements Symfony\Component\Translation\TranslatorInterface correctly.
 */
class MockTranslator implements \Symfony\Component\Translation\TranslatorInterface
{
    public function trans($id, array $parameters = [], $domain = null, $locale = null)
    {
        return ($domain ?: 'no-domain') . ':' . $id;
    }

    public function transChoice($id, $number, array $parameters = [], $domain = null, $locale = null)
    {
        return ($domain ?: 'no-domain') . ':' . $id;
    }

    public function getLocale() { return 'fr'; }
    public function setLocale($locale) {}
    public function setMessages($locale, array $messages) {}
    public function addMessage($locale, $id, $meaning, $domain = null) {}
    public function addLoader($loader) {}
}

try {
    // Setup context
    Context::getContext()->shop = new Shop(1);
    Context::getContext()->language = new Language(1);

    $translator = new MockTranslator();
    $provider = new ProductConditionChoiceProvider($translator);
    $choices = $provider->getChoices();

    echo "Testing ProductConditionChoiceProvider translation domains...\n";

    $expected = [
        ProductCondition::NEW => 'Admin.Catalog.Feature:New',
        ProductCondition::USED => 'Admin.Catalog.Feature:Used',
        ProductCondition::REFURBISHED => 'Admin.Catalog.Feature:Refurbished',
    ];

    $allCorrect = true;
    foreach ($expected as $value => $expectedLabel) {
        $foundLabel = null;
        foreach ($choices as $label => $val) {
            if ($val === $value) {
                $foundLabel = $label;
                break;
            }
        }

        echo "Condition $value: expected '$expectedLabel', got '$foundLabel'\n";

        if ($foundLabel !== $expectedLabel) {
            $allCorrect = false;
        }
    }

    if ($allCorrect) {
        echo "SUCCESS: All condition labels use the correct Admin.Catalog.Feature domain.\n";
        exit(0);
    } else {
        echo "FAILURE: One or more labels use the wrong translation domain.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "FATAL ERROR: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
    exit(1);
}
