<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #31666, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Translation\PrestaShopTranslatorTrait;

/**
 * Mock Catalogue to simulate the Symfony translation catalogue.
 * We make it return true only for the target locale ('fi') to simulate 
 * a translation that exists in Finnish but not in the default language.
 */
class MockCatalogue {
    private $locale;
    public function __construct($locale) {
        $this->locale = $locale;
    }
    public function has($message, $domain) {
        // The bug is triggered when the translation exists in the requested locale 
        // but is missing in the default one.
        return $this->locale === 'fi';
    }
}

/**
 * Base class to simulate the Symfony Translator parent.
 */
class BaseTranslator {
    public function trans($id, array $parameters = [], $domain = null, $locale = null) {
        return "Translated-{$locale}";
    }
}

/**
 * Test class that implements the trait.
 */
class TranslatorTest extends BaseTranslator {
    use PrestaShopTranslatorTrait {
        trans as trait_trans;
    }

    /**
     * Mock the getCatalogue method used by the trait.
     * The trait calls this method to check if a translation exists.
     */
    public function getCatalogue($locale = null) {
        // In the buggy version, $locale is null here.
        // In the fixed version, $locale is passed correctly.
        return new MockCatalogue($locale ?: 'en');
    }
}

try {
    $translator = new TranslatorTest();

    $id = "Test String";
    $domain = "Modules.Localetest.Test";
    $locale = "fi";

    /**
     * EXECUTION
     * 
     * Scenario:
     * - Target locale: 'fi'
     * - Translation exists in 'fi' (MockCatalogue('fi')->has() returns true)
     * - Translation missing in 'en' (MockCatalogue('en')->has() returns false)
     * 
     * Buggy behavior:
     * 1. trait_trans is called with locale 'fi'.
     * 2. shouldFallbackToLegacyModuleTranslation is called WITHOUT $locale.
     * 3. It calls getCatalogue() -> returns MockCatalogue('en').
     * 4. MockCatalogue('en')->has(...) returns false.
     * 5. shouldFallback... returns true -> calls translateUsingLegacySystem().
     * 6. Result is the original string "Test String".
     * 
     * Fixed behavior:
     * 1. trait_trans is called with locale 'fi'.
     * 2. shouldFallbackToLegacyModuleTranslation is called WITH $locale ('fi').
     * 3. It calls getCatalogue('fi') -> returns MockCatalogue('fi').
     * 4. MockCatalogue('fi')->has(...) returns true.
     * 5. shouldFallback... returns false -> calls parent::trans(..., 'fi').
     * 6. Result is "Translated-fi".
     */
    $result = $translator->trait_trans($id, [], $domain, $locale);

    echo "Requested locale: $locale\n";
    echo "Observed result: $result\n";

    if ($result === "Translated-fi") {
        echo "SUCCESS: Translation returned for the requested locale.\n";
        exit(0);
    } else {
        echo "FAILURE: Translation fell back to legacy system/original string.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "ERROR: " . $t->getMessage() . "\n";
    exit(1);
}
