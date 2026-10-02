<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #30258, validé pre/post automatiquement
require 'config/config.inc.php';

try {
    $context = Context::getContext();
    $context->shop = new Shop(1);
    $context->language = new Language(1);
    $context->currency = new Currency(1);

    // 1. Utiliser un client existant pour éviter les erreurs de validation lors de la création (passwd, etc.)
    $customer = new Customer(1);
    if (!Validate::isLoadedObject($customer)) {
        // Fallback si le client 1 n'existe pas : création minimale sans validation stricte sur passwd
        $customer = new Customer();
        $customer->firstname = 'Test';
        $customer->lastname = 'User';
        $customer->email = 'test' . time() . '@example.com';
        $customer->passwd = Tools::hash('password123');
        $customer->add();
    }

    // 2. Simuler un visiteur (Guest) déjà identifié dans le cookie
    // C'est cet ID qui doit être préservé pour que la "dernière visite" soit correcte
    $initialGuestId = 10;
    $context->cookie->id_guest = $initialGuestId;

    // 3. Forcer l'entrée dans le bloc 'else' de updateCustomer
    // Le bug se produit quand PS_CART_FOLLOWING est désactivé ou que les conditions de panier ne sont pas remplies.
    Configuration::updateValue('PS_CART_FOLLOWING', 0);

    // 4. Appeler la méthode touchée par le correctif
    // Avant le correctif, Guest::setNewGuest($this->cookie) est appelé systématiquement dans le else,
    // ce qui écrase l'id_guest existant par un nouvel ID.
    $context->updateCustomer($customer);

    $finalGuestId = (int) $context->cookie->id_guest;

    echo "Initial Guest ID: $initialGuestId\n";
    echo "Final Guest ID: $finalGuestId\n";

    // Le test passe si l'id_guest a été préservé (comportement corrigé)
    // Le test échoue si l'id_guest a été modifié (comportement buggé)
    if ($finalGuestId === $initialGuestId) {
        echo "SUCCESS: Guest ID preserved.\n";
        exit(0);
    } else {
        echo "FAILURE: Guest ID was overwritten by Guest::setNewGuest.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
