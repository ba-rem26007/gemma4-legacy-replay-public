<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33322, validé pre/post automatiquement
require 'config/config.inc.php';

$context = Context::getContext();
$context->shop = new Shop(1);
$context->lang = new Language(1);

try {
    // 1. Créer un nouveau produit pour éviter les contraintes de clés étrangères (commandes, etc.)
    $product = new Product();
    $product->price = 10.0;
    $product->id_tax_rules_group = 1;
    $product->id_category_default = 2;
    $product->name = [1 => 'Test Product'];
    if (!$product->add()) {
        echo "Échec de la création du produit\n";
        exit(1);
    }
    $id_product = (int)$product->id;

    // 2. Insérer manuellement une restriction de transporteur pour ce produit
    // On utilise une référence fixe (1) car la table product_carrier n'a pas de contrainte FK stricte dans le schéma fourni
    $id_carrier_ref = 1;
    $inserted = Db::getInstance()->insert('product_carrier', [
        'id_product' => $id_product,
        'id_carrier_reference' => $id_carrier_ref,
        'id_shop' => 1,
    ]);

    if (!$inserted) {
        echo "Échec de l'insertion dans product_carrier\n";
        exit(1);
    }

    echo "Produit créé : $id_product\n";
    echo "Restriction insérée pour le transporteur $id_carrier_ref\n";

    // Vérification de la présence avant suppression
    $countBefore = (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'product_carrier WHERE id_product = ' . $id_product);
    echo "Restrictions avant suppression : $countBefore\n";

    // 3. Supprimer le produit
    if (!$product->delete()) {
        echo "Échec de la suppression du produit\n";
        exit(1);
    }

    // 4. Vérifier si la restriction a été nettoyée dans la table product_carrier
    $countAfter = (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'product_carrier WHERE id_product = ' . $id_product);
    echo "Restrictions après suppression : $countAfter\n";

    // Le test passe si le compte est à 0 (le correctif a supprimé la ligne)
    exit($countAfter === 0 ? 0 : 1);

} catch (\Throwable $t) {
    echo "Erreur fatale : " . $t->getMessage() . "\n";
    exit(1);
}
