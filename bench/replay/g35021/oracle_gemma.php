<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #35021, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

// 1. Use existing demo customer
$customer = new Customer(1);
if (!Validate::isLoadedObject($customer)) {
    $customer = new Customer();
    $customer->firstname = 'Test';
    $customer->lastname = 'User';
    $customer->email = 'test@example.com';
    $customer->passwd = Password::hash('password123');
    $customer->add();
}
$context->customer = $customer;

// 2. Create Countries
// We need a valid id_zone. Usually 1 is the default zone.
$id_zone = 1;

// France: No states
$countryFrance = new Country();
$countryFrance->id_zone = $id_zone;
$countryFrance->iso_code = 'FR';
$countryFrance->contains_states = 0;
$countryFrance->need_identification_number = 0;
$countryFrance->display_tax_label = 0;
$countryFrance->name = 'France';
$countryFrance->add();

// USA: Has states
$countryUSA = new Country();
$countryUSA->id_zone = $id_zone;
$countryUSA->iso_code = 'US';
$countryUSA->contains_states = 1;
$countryUSA->need_identification_number = 0;
$countryUSA->display_tax_label = 0;
$countryUSA->name = 'USA';
$countryUSA->add();

// Set USA as default country to trigger the bug (fallback)
Configuration::updateValue('PS_COUNTRY_DEFAULT', (int)$countryUSA->id);

// 3. Create an Address in France
$address = new Address();
$address->id_customer = $customer->id;
$address->id_country = $countryFrance->id;
$address->alias = 'Home';
$address->firstname = 'Test';
$address->lastname = 'User';
$address->address1 = '123 Rue de Paris';
$address->city = 'Paris';
$address->add();

// 4. Mock the Formatter
$formatter = new class {
    public $country;
    public function setCountry($country) {
        $this->country = $country;
    }
    public function getCountry() {
        return $this->country ?: new Country();
    }
};

// 5. Instantiate CustomerAddressForm
require_once 'classes/form/Form.php';
require_once 'classes/form/CustomerAddressForm.php';

$form = new CustomerAddressForm(
    $context->language,
    null, // persister
    null, // smarty
    null, // translator
    $formatter
);

try {
    // Simulate the "Update Address" scenario: the form already has the address object
    $form->address = $address;

    // First call: simulate the initial load where id_country is passed in params
    // This sets the formatter country to France.
    $form->fillWith(['id_country' => $countryFrance->id]);
    $countryAfterFirstFill = $formatter->getCountry();
    echo "Country after first fillWith(['id_country' => ...]): " . ($countryAfterFirstFill ? $countryAfterFirstFill->id : 'null') . " (Expected: " . $countryFrance->id . ")\n";

    // Second call: simulate the bug where fillWith is called without params (e.g. during a re-render)
    // Before fix: it ignores $this->address and falls back to PS_COUNTRY_DEFAULT (USA)
    // After fix: it sees $this->address is set and preserves the current formatter country (France)
    $form->fillWith([]);
    $countryAfterSecondFill = $formatter->getCountry();
    echo "Country after second fillWith([]): " . ($countryAfterSecondFill ? $countryAfterSecondFill->id : 'null') . " (Expected: " . $countryFrance->id . ")\n";

    if ($countryAfterSecondFill && (int)$countryAfterSecondFill->id === (int)$countryFrance->id) {
        echo "SUCCESS: Country preserved.\n";
        exit(0);
    } else {
        echo "FAILURE: Country was reset to default (USA) or lost.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
