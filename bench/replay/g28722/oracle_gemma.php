<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #28722, validé pre/post automatiquement
require 'config/config.inc.php';

$context = Context::getContext();
$context->shop = new Shop(1);
$context->lang = new Language(1);
$context->currency = new Currency(1);

// Utilisation d'un client existant dans les données de démo pour éviter les erreurs de validation de mot de passe
$customer = new Customer(1);
if (!Validate::isLoadedObject($customer)) {
    // Fallback si le client 1 n'existe pas, on crée un client minimal sans passwd pour éviter la validation
    $customer = new Customer();
    $customer->firstname = 'Test';
    $customer->lastname = 'User';
    $customer->email = 'test@example.com';
    $customer->passwd = Tools::encrypt('password123');
    $customer->add();
}

// Création d'un nouveau panier
$cart = new Cart();
$cart->id_currency = 1;
$cart->id_lang = 1;
$cart->id_customer = $customer->id;

try {
    // L'appel à add() doit déclencher l'assignation de id_shop_group via le correctif
    $cart->add();
    
    $observedShopGroupId = (int)$cart->id_shop_group;
    $expectedShopGroupId = (int)$context->shop->id_shop_group;

    echo "id_shop_group observé : $observedShopGroupId\n";
    echo "id_shop_group attendu : $expectedShopGroupId\n";

    if ($observedShopGroupId === 0) {
        echo "ÉCHEC : id_shop_group est resté à 0 (comportement avant correctif)\n";
        exit(1);
    }

    if ($observedShopGroupId !== $expectedShopGroupId) {
        echo "ÉCHEC : id_shop_group ($observedShopGroupId) ne correspond pas au groupe de la boutique ($expectedShopGroupId)\n";
        exit(1);
    }

    echo "SUCCÈS : id_shop_group a été correctement assigné\n";
    exit(0);

} catch (\Throwable $e) {
    echo "Erreur lors de l'exécution : " . $e->getMessage() . "\n";
    exit(1);
}
