<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #27803, validé pre/post automatiquement
require 'config/config.inc.php';

$context = Context::getContext();
$context->shop = 1;
$context->language = 1;

// Utilisation du client de démo 1
$customer = new Customer(1);
if (!Validate::isLoadedObject($customer)) {
    $customer = new Customer();
    $customer->firstname = 'Test';
    $customer->lastname = 'User';
    $customer->email = 'test@example.com';
    $customer->passwd = 'password';
    $customer->add();
}

// S'assurer que le client a une adresse pour éviter des erreurs dans updateCustomer
if (!Address::getFirstCustomerAddressId((int)$customer->id)) {
    $address = new Address();
    $address->id_customer = (int)$customer->id;
    $address->firstname = $customer->firstname;
    $address->lastname = $customer->lastname;
    $address->address1 = '123 Test St';
    $address->city = 'Test City';
    $address->id_country = 1;
    $address->add();
}

// On force la configuration pour entrer dans le bloc 'else' de updateCustomer
Configuration::updateValue('PS_CART_FOLLOWING', 0);

// Simulation : l'utilisateur n'est pas connecté et n'a pas de panier chargé en base
// On instancie un objet Cart vide (non sauvegardé, id = 0)
$context->cookie->id_cart = 0;
$context->cart = new Cart();
$context->cart->id_currency = 1;
$context->cart->id_lang = 1;

echo "Cart ID before login: " . (int)$context->cart->id . "\n";

try {
    // Appel direct de la méthode touchée par le correctif
    $context->updateCustomer($customer);
} catch (\Throwable $t) {
    echo "Exception caught: " . $t->getMessage() . "\n";
    exit(1);
}

$cartId = (int)$context->cart->id;
echo "Cart ID after login: $cartId\n";

// Comportement attendu : 
// Avant correctif : $cartId > 0 (un panier vide a été créé et sauvegardé)
// Après correctif : $cartId === 0 (aucun panier n'est créé tant qu'aucun produit n'est ajouté)
exit($cartId === 0 ? 0 : 1);
