<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #36807, validé pre/post automatiquement
require 'config/config.inc.php';

// On convertit les warnings en exceptions pour détecter le bug "Trying to access array offset on value of type null"
set_error_handler(function ($errno, $errstr) {
    throw new Exception($errstr);
});

try {
    // Setup : On utilise le produit 1 et la catégorie 2
    $id_product = 1;
    $id_category = 2;

    $product = new Product($id_product);
    $product->id_category_default = $id_category;
    $product->save();

    // On s'assure que le produit est bien lié à la catégorie dans ps_category_product
    Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'category_product WHERE id_product = ' . (int)$id_product);
    Db::getInstance()->insert('category_product', [
        'id_category' => (int)$id_category,
        'id_product' => (int)$id_product,
        'position' => 1,
    ]);

    echo "Test de setWsPositionInCategory pour le produit $id_product dans la catégorie $id_category...\n";

    // On appelle la méthode qui contient le bug. 
    // On passe une position > 0 pour entrer dans la logique de traitement du tableau $result.
    $result = $product->setWsPositionInCategory(1);

    echo "Succès : aucune erreur PHP détectée.\n";
    exit(0);

} catch (\Throwable $t) {
    echo "Erreur détectée : " . $t->getMessage() . "\n";
    // Si on attrape l'erreur "Trying to access array offset on value of type null", c'est que le bug est présent.
    exit(1);
}
