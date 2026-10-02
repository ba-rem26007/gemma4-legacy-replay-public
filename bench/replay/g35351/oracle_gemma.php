<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #35351, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Test de non-régression pour le bug de conversion des chaînes vides en NULL
 * lors de la sauvegarde de la base de données (PrestaShopBackup).
 */

// 1. Configuration de l'environnement de backup
if (!defined('_PS_ADMIN_DIR_')) {
    define('_PS_ADMIN_DIR_', _PS_ROOT_DIR_ . 'admin/');
}

// On définit un dossier de backup spécifique et on s'assure qu'il existe
$backupDirName = 'backup_test_regression/';
PrestaShopBackup::$backupDir = $backupDirName;
$fullBackupPath = _PS_ADMIN_DIR_ . $backupDirName;

if (!is_dir($fullBackupPath)) {
    mkdir($fullBackupPath, 0777, true);
}

// 2. Préparation des données : on utilise le produit 1
// On force des chaînes vides sur des champs qui sont NULL par défaut en BDD
$p = new Product(1);
$p->reference = 'REGRESSION_BACKUP_TEST';
$p->isbn = ''; 
$p->upc = '';  
$p->mpn = '';  
$p->save();

// 3. Exécution du backup
$backup = new PrestaShopBackup();
$backup->id = 'test_nulls';
$backup->psBackupAll = true;
$backup->psBackupDropTable = true;

try {
    // La méthode add() retourne le chemin complet du fichier créé ($backupdir . $filename)
    $filePath = $backup->add();

    if (!$filePath || !file_exists($filePath)) {
        echo "Erreur : Le fichier de backup n'a pas été créé ou chemin invalide : $filePath\n";
        exit(1);
    }

    $content = file_get_contents($filePath);
    
    // 4. Analyse du résultat
    $foundLine = false;
    $lines = explode("\n", $content);
    foreach ($lines as $line) {
        // On cible la ligne d'insertion du produit de test via sa référence
        if (strpos($line, 'INSERT INTO `' . _DB_PREFIX_ . 'product`') !== false && strpos($line, 'REGRESSION_BACKUP_TEST') !== false) {
            $foundLine = true;
            
            // Le bug remplace les '' des champs nullables par NULL.
            // Si le bug est présent, on trouvera "NULL" et AUCUN "''" pour ces champs.
            $hasEmptyString = (strpos($line, "''") !== false);
            $hasNull = (strpos($line, "NULL") !== false);
            
            echo "Ligne produit trouvée. Contient '' : " . ($hasEmptyString ? 'OUI' : 'NON') . " | Contient NULL : " . ($hasNull ? 'OUI' : 'NON') . "\n";
            
            /**
             * Assertion :
             * - Avant correctif : isbn='' devient NULL. La ligne contient NULL et PAS de ''.
             * - Après correctif : isbn='' reste ''. La ligne contient ''.
             */
            if ($hasEmptyString) {
                echo "Succès : Les chaînes vides sont préservées.\n";
                exit(0);
            } else {
                echo "Échec : Les chaînes vides ont été converties en NULL.\n";
                exit(1);
            }
        }
    }

    if (!$foundLine) {
        echo "Erreur : La ligne d'insertion du produit de test n'a pas été trouvée dans le fichier SQL.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Exception capturée : " . $t->getMessage() . "\n";
    exit(1);
}
