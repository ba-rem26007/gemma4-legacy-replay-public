<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29122, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Dummy Translator to satisfy TranslatorInterface requirements
 * without relying on the Symfony container or specific adapter classes
 * that might not be autoloaded in this specific CLI environment.
 */
class DummyTranslator implements \Symfony\Component\Translation\TranslatorInterface
{
    public function translate($string, array $parameters = [], $domain = null, $locale = null) { return $string; }
    public function getLocale() { return 'fr'; }
    public function setLocale($locale) { }
    public function setMessages($locale, array $messages) { }
    public function addMessage($locale, $id, $message, $domain = null) { }
    public function addLoader($locale, $loader) { }
}

try {
    // 1. Setup Context
    $context = Context::getContext();
    $context->shop = new Shop(1);
    $context->language = new Language(1);
    $context->currency = new Currency(1);
    $context->customer = new Customer(1);

    // 2. Ensure necessary demo data exists
    $id_zone = 1; 

    // Italy Country
    $id_italy = (int)Country::getByIso('IT', true);
    if (!$id_italy) {
        $it = new Country();
        $it->id_zone = $id_zone;
        $it->iso_code = 'IT';
        $it->contains_states = 0;
        $it->need_identification_number = 1;
        $it->display_tax_label = 0;
        $it->id_currency = 1;
        $it->name = ['1' => 'Italy'];
        $it->add();
        $id_italy = $it->id;
    }

    // USA Country (for browser detection)
    $id_us = (int)Country::getByIso('US', true);
    if (!$id_us) {
        $us = new Country();
        $us->id_zone = $id_zone;
        $us->iso_code = 'US';
        $us->contains_states = 1;
        $us->need_identification_number = 0;
        $us->display_tax_label = 0;
        $us->id_currency = 1;
        $us->name = ['1' => 'United States'];
        $us->add();
        $id_us = $us->id;
    }

    // 3. Trigger the bug conditions
    // Enable browser detection in configuration
    Configuration::updateValue('PS_COUNTRY_BROWSER_DETECTION', 1);
    // Simulate browser header for US
    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9';

    // 4. Instantiate the Form and its dependencies
    $smarty = new Smarty();
    $language = new Language(1);
    $translator = new DummyTranslator();
    $formatter = new CustomerAddressFormatter($language, $translator);
    $persister = new CustomerAddressPersister($language, $translator);
    
    $form = new CustomerAddressForm($smarty, $language, $translator, $persister, $formatter);

    // 5. Execute the code touched by the fix
    // We pass id_country = Italy, but browser detection is set to US.
    // Before fix: Browser detection takes priority -> Formatter gets US Country.
    // After fix: id_country takes priority -> Formatter gets Italy.
    $form->fillWith(['id_country' => $id_italy]);

    $observed_country_id = (int)$form->formatter->getCountry()->id;

    echo "Requested Country ID (Italy): $id_italy\n";
    echo "Browser Detection Country ID (US): $id_us\n";
    echo "Observed Country ID in Formatter: $observed_country_id\n";

    // The test passes if the requested id_country is respected regardless of browser detection
    if ($observed_country_id === $id_italy) {
        exit(0);
    } else {
        echo "Bug detected: Browser detection overrode the requested country.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Exception: " . $t->getMessage() . "\n";
    exit(1);
}
