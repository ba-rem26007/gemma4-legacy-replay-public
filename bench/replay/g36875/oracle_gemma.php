<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #36875, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

try {
    // Use demo product 1 which is guaranteed to exist
    $id_product = 1;
    $product = new Product($id_product);
    
    if (!Validate::isLoadedObject($product)) {
        echo "Erreur : Produit 1 non trouvé dans les données de démo.\n";
        exit(1);
    }

    // The 'active' column in ps_product is tinyint(1)
    // The 'id_product' column is int(10) unsigned
    $sql = 'SELECT id_product, active FROM ' . _DB_PREFIX_ . 'product WHERE id_product = ' . (int)$id_product;
    $results = Db::getInstance()->executeS($sql);

    if (!$results || !isset($results[0])) {
        echo "Erreur : Aucun résultat retourné par la requête.\n";
        exit(1);
    }

    $val_id = $results[0]['id_product'];
    $type_id = gettype($val_id);
    $val_active = $results[0]['active'];
    $type_active = gettype($val_active);

    echo "id_product: $val_id (type: $type_id)\n";
    echo "active: $val_active (type: $type_active)\n";

    // The bug: PHP 8.1 returns integers for tinyint/unsignedInt.
    // The fix: PDO::ATTR_STRINGIFY_FETCHES => true forces them to be strings.
    if ($type_id === 'string' && $type_active === 'string') {
        echo "Succès : Les valeurs numériques sont retournées sous forme de chaînes de caractères.\n";
        exit(0);
    } else {
        echo "Échec : Au moins une valeur est retournée comme un entier (comportement PHP 8.1 non corrigé).\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Exception capturée : " . $t->getMessage() . "\n";
    exit(1);
}
