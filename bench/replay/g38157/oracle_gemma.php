<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #38157, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Translation\PrestaShopTranslatorTrait;
use Symfony\Component\Translation\Translator;

/**
 * Simple mock for the Catalogue object to avoid dependency on 
 * specific Symfony Catalogue implementation classes.
 */
class MockCatalogue
{
    public $hasValue = false;
    public function has($id, $domain)
    {
        return $this->hasValue;
    }
}

/**
 * Mock class to test the PrestaShopTranslatorTrait.
 */
class TestTranslator extends Translator
{
    use PrestaShopTranslatorTrait;

    /**
     * Overriding getCatalogue to return our mock catalogues.
     * Must be public to match Symfony\Component\Translation\Translator.
     */
    public function getCatalogue($locale = null)
    {
        $cat = new MockCatalogue();
        // We simulate that the translation exists in English (default) 
        // but is missing in Italian.
        if ($locale === 'en-US' || (empty($locale) && $this->getLocale() === 'en-US')) {
            $cat->hasValue = true;
        } else {
            $cat->hasValue = false;
        }
        return $cat;
    }
}

// Setup Context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

try {
    // 1. Instantiate the translator with English as default locale
    $translator = new TestTranslator('en-US');

    $message = 'Message ENG';
    $domain = 'Modules.Translationtest.Home';
    $localeIt = 'it-IT';

    // 2. Use Reflection to test the private method 'shouldFallbackToLegacyModuleTranslation'
    $reflection = new ReflectionClass($translator);
    $method = $reflection->getMethod('shouldFallbackToLegacyModuleTranslation');
    $method->setAccessible(true);

    /**
     * TEST CASE:
     * Default Locale: en-US (Translation EXISTS)
     * Requested Locale: it-IT (Translation MISSING)
     * 
     * Before fix: 
     * The method called $this->getCatalogue() without arguments.
     * This returned the catalogue for 'en-US', which HAS the message.
     * Result: shouldFallback = FALSE (Bug: it doesn't fallback to legacy for Italian).
     * 
     * After fix: 
     * The method calls $this->getCatalogue($locale) with 'it-IT'.
     * This returns the catalogue for 'it-IT', which DOES NOT have the message.
     * Result: shouldFallback = TRUE (Fixed: it correctly falls back to legacy).
     */
    
    // We pass the requested locale 'it-IT'.
    $shouldFallback = $method->invokeArgs($translator, [$message, $domain, $localeIt]);

    echo "Message: $message\n";
    echo "Domain: $domain\n";
    echo "Requested Locale: $localeIt\n";
    echo "Should fallback to legacy: " . ($shouldFallback ? 'YES' : 'NO') . "\n";

    // The test passes if it correctly identifies that it should fallback to legacy 
    // for the Italian locale, even though the English locale has the translation.
    exit($shouldFallback ? 0 : 1);

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
