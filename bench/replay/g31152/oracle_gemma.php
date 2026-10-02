<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #31152, validé pre/post automatiquement
require 'config/config.inc.php';

try {
    // 1. Setup: Create two countries with different zip code formats
    // id_zone is required for Country ObjectModel
    $countryFR = new Country();
    $countryFR->id_zone = 1; 
    $countryFR->iso_code = 'FR';
    $countryFR->zip_code_format = 'NNNNN';
    $countryFR->active = 1;
    $countryFR->contains_states = 0;
    $countryFR->need_identification_number = 0;
    $countryFR->display_tax_label = 1;
    $countryFR->name = array(1 => 'France');
    $countryFR->add();

    $countryCH = new Country();
    $countryCH->id_zone = 1;
    $countryCH->iso_code = 'CH';
    $countryCH->zip_code_format = 'NNNN';
    $countryCH->active = 1;
    $countryCH->contains_states = 0;
    $countryCH->need_identification_number = 0;
    $countryCH->display_tax_label = 1;
    $countryCH->name = array(1 => 'Switzerland');
    $countryCH->add();

    // 2. Configure the environment to trigger the bug
    // Set global default country to France
    Configuration::updateValue('PS_COUNTRY_DEFAULT', (int)$countryFR->id);

    $language = new Language(1);
    
    // Mock the Translator
    $translator = new class {
        public function trans($id, $params, $domain) {
            return $id;
        }
    };
    
    // Instantiate the formatter. 
    // Based on the error, the constructor is: __construct(Country $country, Language $language)
    $formatter = new CustomerAddressFormatter($countryCH, $language);

    // Instantiate the form. 
    // Constructor: __construct($smarty, $translator, $formatter, $language, $persister)
    $form = new CustomerAddressForm(
        null,           // smarty
        $translator, 
        $formatter, 
        $language, 
        null            // persister
    );

    // 3. Trigger the bug:
    // The user is adding an address for Switzerland.
    // The 'id_country' in params matches the current formatter country (Switzerland).
    // BEFORE FIX: The condition (int) $params['id_country'] !== (int) $this->formatter->getCountry()->id 
    // is FALSE. The code skips the first block and falls into the 'else', 
    // resetting the country to PS_COUNTRY_DEFAULT (France).
    // AFTER FIX: The code correctly handles the case where id_country is provided, 
    // even if it matches the current formatter country.
    $params = [
        'id_country' => (int)$countryCH->id,
        'postcode' => '1234', // Valid Swiss postcode (4 digits), invalid French (5 digits)
        'city' => 'Zurich',
        'address1' => 'Street 1',
        'firstname' => 'John',
        'lastname' => 'Doe',
    ];

    $form->fillWith($params);

    echo "Selected Country ID: " . $params['id_country'] . "\n";
    echo "Formatter Country ID after fillWith: " . $formatter->getCountry()->id . "\n";
    echo "Formatter Country Format: " . $formatter->getCountry()->zip_code_format . "\n";
    echo "Postcode value: " . $params['postcode'] . "\n";

    // The validate() method uses the country currently set in the formatter to check the zip code.
    $isValid = $form->validate();
    echo "Form is valid: " . ($isValid ? 'YES' : 'NO') . "\n";

    // If the bug is present, the formatter was reset to France (NNNNN), 
    // so '1234' is invalid. If fixed, it stays Switzerland (NNNN), so '1234' is valid.
    exit($isValid ? 0 : 1);

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
    exit(1);
}
