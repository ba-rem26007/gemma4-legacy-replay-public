<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #36895, validé pre/post automatiquement
require 'config/config.inc.php';

try {
    // 1. Activer le Multistore
    Configuration::updateValue('PS_MULTISHOP_ACTIVE', 1);

    // 2. Créer une deuxième boutique (Shop 2)
    $shop2 = new Shop();
    $shop2->id_shop_group = 1;
    $shop2->name = 'Shop 2';
    $shop2->active = 1;
    $shop2->id_category = 2; // Catégorie racine obligatoire
    $shop2->add();
    $id_shop2 = (int)$shop2->id;

    // 3. Utiliser un client existant ou en créer un
    $customer = new Customer(1);
    if (!Validate::isLoadedObject($customer)) {
        $customer = new Customer();
        $customer->firstname = 'Test';
        $customer->lastname = 'Test';
        $customer->email = 'test@test.com';
        $customer->passwd = Tools::encrypt('123456');
        $customer->add();
    }
    $id_customer = (int)$customer->id;

    // 4. Créer un produit associé EXCLUSIVEMENT à la boutique 2
    // En définissant le shop dans le contexte avant l'ajout, PrestaShop 
    // crée l'entrée correspondante dans ps_product_shop.
    Context::getContext()->shop = $shop2;
    $p = new Product();
    $p->price = 10.00;
    $p->name = [1 => 'Produit Shop 2'];
    $p->link_rewrite = [1 => 'produit-shop-2'];
    $p->active = 1;
    $p->add();
    $id_product = (int)$p->id;

    // 5. Créer un panier dans la boutique 2 avec ce produit
    $cart = new Cart();
    $cart->id_currency = 1;
    $cart->id_lang = 1;
    $cart->id_shop = $id_shop2;
    $cart->id_customer = $id_customer;
    $cart->add();
    $cart->updateQty(1, $id_product);
    $id_cart = (int)$cart->id;

    // 6. Simuler le contexte "All shops" en se plaçant sur Shop 1
    // Le bug survient car AdminCartsController::getOrderTotalUsingTaxCalculationMethod
    // ne changeait pas le shop du contexte pour correspondre à celui du panier.
    Context::getContext()->shop = new Shop(1);

    require_once 'controllers/admin/AdminCartsController.php';

    echo "Test de calcul du total du panier $id_cart (Shop 2) avec contexte Shop 1...\n";
    
    // Appel de la méthode statique touchée par le correctif
    $total = AdminCartsController::getOrderTotalUsingTaxCalculationMethod($id_cart);
    
    echo "Total observé : $total\n";

    // Si le correctif est appliqué, le total doit être > 0 car le contexte shop 
    // est correctement basculé sur Shop 2 à l'intérieur de la méthode.
    if ($total > 0) {
        exit(0);
    } else {
        echo "Le total est 0 : le produit n'a pas été trouvé car le contexte shop est resté sur Shop 1.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Erreur capturée : " . $t->getMessage() . "\n";
    echo "Fichier : " . $t->getFile() . " ligne " . $t->getLine() . "\n";
    exit(1);
}
