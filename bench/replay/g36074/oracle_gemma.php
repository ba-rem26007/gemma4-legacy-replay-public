<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #36074, validé pre/post automatiquement
require 'config/config.inc.php';

try {
    // 1. Setup Context
    $id_lang = 1;
    Context::getContext()->shop = new Shop(1);
    Context::getContext()->language = new Language($id_lang);
    Context::getContext()->currency = new Currency(1);

    $db = Db::getInstance();

    // 2. Create Zones and Countries using direct SQL to bypass ObjectModel validation (preg_match errors)
    // Zone 1: Europe, Zone 2: South America
    $db->insert('zone', []); 
    $id_zone1 = (int)$db->Insert_ID();
    
    $db->insert('zone', []); 
    $id_zone2 = (int)$db->Insert_ID();

    // Country 1: France (Zone 1)
    $db->insert('country', [
        'id_zone' => $id_zone1,
        'iso_code' => 'FR',
        'active' => 1,
        'contains_states' => 0,
        'need_identification_number' => 0,
        'display_tax_label' => 1
    ]);
    $id_country1 = (int)$db->Insert_ID();
    $db->insert('country_lang', [
        'id_country' => $id_country1,
        'id_lang' => $id_lang,
        'name' => 'France'
    ]);

    // Country 2: Brazil (Zone 2)
    $db->insert('country', [
        'id_zone' => $id_zone2,
        'iso_code' => 'BR',
        'active' => 1,
        'contains_states' => 0,
        'need_identification_number' => 0,
        'display_tax_label' => 1
    ]);
    $id_country2 = (int)$db->Insert_ID();
    $db->insert('country_lang', [
        'id_country' => $id_country2,
        'id_lang' => $id_lang,
        'name' => 'Brazil'
    ]);

    // 3. Create Carriers using direct SQL
    // Carrier 1: Only for Zone 1
    $db->insert('carrier', [
        'id_reference' => 100,
        'active' => 1,
        'shipping_handling' => 1
    ]);
    $id_carrier1 = (int)$db->Insert_ID();
    $db->insert('carrier_lang', [
        'id_carrier' => $id_carrier1,
        'id_lang' => $id_lang,
        'name' => 'Carrier Europe'
    ]);

    // Carrier 2: Only for Zone 2
    $db->insert('carrier', [
        'id_reference' => 101,
        'active' => 1,
        'shipping_handling' => 1
    ]);
    $id_carrier2 = (int)$db->Insert_ID();
    $db->insert('carrier_lang', [
        'id_carrier' => $id_carrier2,
        'id_lang' => $id_lang,
        'name' => 'Carrier South America'
    ]);

    // Define coverage in ps_delivery
    $db->insert('delivery', [
        'id_carrier' => $id_carrier1,
        'id_zone' => $id_zone1,
        'price' => 5.00
    ]);
    $db->insert('delivery', [
        'id_carrier' => $id_carrier2,
        'id_zone' => $id_zone2,
        'price' => 5.00
    ]);

    // 4. Setup Products and Restrictions
    // Product 1 restricted to Carrier 1
    $p1 = new Product(1);
    $p1->price = 10.00;
    $p1->save();
    $db->delete('product_carrier', 'id_product = ' . (int)$p1->id);
    $db->insert('product_carrier', [
        'id_product' => (int)$p1->id,
        'id_carrier' => $id_carrier1
    ]);

    // Product 2 restricted to Carrier 2
    $p2 = new Product(2);
    $p2->price = 10.00;
    $p2->save();
    $db->delete('product_carrier', 'id_product = ' . (int)$p2->id);
    $db->insert('product_carrier', [
        'id_product' => (int)$p2->id,
        'id_carrier' => $id_carrier2
    ]);

    // 5. Setup Customer and Address in Zone 1 (France)
    $customer = new Customer(1);
    $address = new Address();
    $address->id_customer = $customer->id;
    $address->id_country = $id_country1;
    $address->alias = 'Home';
    $address->firstname = 'John';
    $address->lastname = 'Doe';
    $address->address1 = '123 Street';
    $address->city = 'Paris';
    $address->add();

    // 6. Create Cart and add both products
    $cart = new Cart();
    $cart->id_currency = 1;
    $cart->id_lang = $id_lang;
    $cart->id_address_delivery = $address->id;
    $cart->add();
    $cart->updateQty(1, 1); // Product 1 (shippable to Zone 1 via Carrier 1)
    $cart->updateQty(2, 1); // Product 2 (NOT shippable to Zone 1 because Carrier 2 is Zone 2 only)
    $cart->update();

    // 7. Test getDeliveryOptionList
    // Product 2 is restricted to Carrier 2, but Carrier 2 doesn't cover Zone 1.
    // This forces getPackageList() to create a package for Product 2 with no available carrier.
    // The fix should detect this and return an empty list of delivery options.
    $options = $cart->getDeliveryOptionList();

    echo "Number of delivery options found: " . count($options) . "\n";

    // If fixed, it must return an empty array.
    exit(empty($options) ? 0 : 1);

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
