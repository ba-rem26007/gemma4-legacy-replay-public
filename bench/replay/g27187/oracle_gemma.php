<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #27187, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Core\Form\CustomerAddressFormatter;
use PrestaShop\PrestaShop\Core\Form\CustomerAddressPersister;

try {
    // 1. Setup: Ensure France and Spain exist in the database
    $id_france = (int) Country::getByIso('FR');
    if (!$id_france) {
        $c = new Country();
        $c->iso_code = 'FR';
        $c->name = 'France';
        $c->active = 1;
        $c->contains_states = 0;
        $c->need_identification_number = 0;
        $c->display_tax_label = 0;
        $c->add();
        $id_france = $c->id;
    }

    $id_spain = (int) Country::getByIso('ES');
    if (!$id_spain) {
        $c = new Country();
        $c->iso_code = 'ES';
        $c->name = 'Spain';
        $c->active = 1;
        $c->contains_states = 0;
        $c->need_identification_number = 0;
        $c->display_tax_label = 0;
        $c->add();
        $id_spain = $c->id;
    }

    // 2. Configure BO: Enable browser detection and set default country to Spain
    Configuration::updateValue('PS_DETECT_COUNTRY', 1);
    Configuration::updateValue('PS_COUNTRY', $id_spain);

    // 3. Simulate browser header (French browser)
    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7';

    // 4. Instantiate dependencies for CustomerAddressForm
    $smarty = new Smarty();
    $lang = new Language();
    
    // Mock TranslatorInterface with exact Symfony signatures to avoid Fatal Errors
    $translator = new class implements \Symfony\Component\Translation\TranslatorInterface {
        public function trans($id, array $parameters = [], $domain = null, $locale = null) { return $id; }
        public function transChoice($id, $number, array $parameters = [], $domain = null, $locale = null) { return $id; }
        public function getCatalogue() { return []; }
        public function setLocale($locale) {}
        public function getLocale() { return 'fr'; }
        public function setFallbackLocale($locale) {}
        public function getFallbackLocale() { return 'en'; }
    };

    // Instantiate src/ classes
    // Formatter needs a default country and language
    $formatter = new CustomerAddressFormatter(new Country($id_spain), $lang);
    $persister = new CustomerAddressPersister();

    // Instantiate the form
    $form = new CustomerAddressForm($smarty, $lang, $translator, $persister, $formatter);

    // 5. Trigger the logic: fill the form
    // This is where the fix in CustomerAddressForm::fillWith() should act
    $form->fillWith([]);

    // 6. Diagnosis
    $detectedCountryId = (int) $form->formatter->getCountry()->id;
    echo "Default country (BO): $id_spain\n";
    echo "Detected country (Browser): $detectedCountryId\n";
    echo "Expected country: $id_france\n";

    // The test passes if the browser detection overrode the BO default country
    exit($detectedCountryId === $id_france ? 0 : 1);

} catch (\Throwable $t) {
    echo "Fatal Error: " . $t->getMessage() . "\n";
    exit(1);
}
