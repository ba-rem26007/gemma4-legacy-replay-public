<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #32290, validé pre/post automatiquement
require 'config/config.inc.php';

$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

try {
    // 1. Utilisation d'un client de démo existant
    $customer = new Customer(1);
    if (!Validate::isLoadedObject($customer)) {
        $customer = new Customer();
        $customer->firstname = 'Test';
        $customer->lastname = 'User';
        $customer->email = 'test_dni@example.com';
        $customer->passwd = Passwd::hash('password123');
        $customer->add();
    }

    // 2. Récupération d'un pays existant
    $id_country = (int)Db::getInstance()->getValue('SELECT id_country FROM ' . _DB_PREFIX_ . 'country LIMIT 1');
    if ($id_country === 0) {
        exit(1); // Erreur fatale : aucun pays en base
    }

    // 3. Rendre le champ DNI obligatoire pour ce pays via SQL
    // On utilise Db::getInstance() car la classe RequiredField n'est pas disponible/autoloadée
    Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'required_field WHERE `name` = "dni" AND `id_country` = ' . (int)$id_country);
    Db::getInstance()->execute('INSERT INTO ' . _DB_PREFIX_ . 'required_field (`name`, `id_country`) VALUES ("dni", ' . (int)$id_country . ')');

    // 4. Création d'une adresse avec le champ DNI vide
    $address = new Address();
    $address->id_customer = $customer->id;
    $address->id_country = $id_country;
    $address->firstname = 'Test';
    $address->lastname = 'User';
    $address->address1 = '123 Rue de Test';
    $address->city = 'Paris';
    $address->alias = 'Home';
    $address->dni = ''; // Champ obligatoire laissé vide
    $address->add();

    // 5. Appel direct au code touché par le correctif
    $checksumCore = new AddressChecksumCore();
    
    echo "Tentative de génération du checksum pour une adresse avec DNI manquant...\n";
    
    // Avant le correctif, $address->getFields() (appelé par generateChecksum) 
    // lance une exception si un champ défini dans ps_required_field est vide.
    // Après le correctif, l'exception est rattrapée et retourne un hash par défaut.
    $checksum = $checksumCore->generateChecksum($address);
    
    echo "Checksum généré avec succès : $checksum\n";
    exit(0); // Le comportement est CORRIGÉ

} catch (\Throwable $e) {
    echo "Exception capturée : " . $e->getMessage() . "\n";
    exit(1); // Le bug est présent (l'exception remonte)
}
