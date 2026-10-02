<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33428, validé pre/post automatiquement
require 'config/config.inc.php';

// Configuration du contexte pour éviter les erreurs de AdminController
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

// AdminController vérifie si l'employé est connecté dans son constructeur
$employee = new Employee(1);
$context->employee = $employee;

// On s'assure que le contrôleur est chargé
require_once 'controllers/admin/AdminImagesController.php';

try {
    // Instanciation du contrôleur
    $controller = new AdminImagesController();

    // Utilisation de la Reflection pour modifier la propriété protégée canGenerateAvif
    $reflection = new ReflectionClass($controller);
    $property = $reflection->getProperty('canGenerateAvif');
    $property->setAccessible(true);
    $property->setValue($controller, false); // On simule l'absence de support AVIF

    // Cas de test : l'utilisateur tente d'activer AVIF et oublie JPG
    $_POST['PS_IMAGE_FORMAT'] = ['avif'];

    echo "Valeur initiale de PS_IMAGE_FORMAT : " . implode(', ', $_POST['PS_IMAGE_FORMAT']) . "\n";
    echo "Support AVIF simulé : Non\n";

    // Le correctif ajoute la méthode beforeUpdateOptions()
    if (method_exists($controller, 'beforeUpdateOptions')) {
        $controller->beforeUpdateOptions();
    } else {
        echo "La méthode beforeUpdateOptions n'existe pas (code avant correctif).\n";
        exit(1);
    }

    $finalFormats = $_POST['PS_IMAGE_FORMAT'];
    echo "Valeur finale de PS_IMAGE_FORMAT : " . implode(', ', $finalFormats) . "\n";

    // Vérifications :
    // 1. 'avif' doit être supprimé car canGenerateAvif est false
    // 2. 'jpg' doit être présent car il est obligatoire
    $hasAvif = in_array('avif', $finalFormats);
    $hasJpg = in_array('jpg', $finalFormats);

    if ($hasAvif) {
        echo "ÉCHEC : AVIF est toujours présent alors qu'il n'est pas supporté.\n";
        exit(1);
    }

    if (!$hasJpg) {
        echo "ÉCHEC : JPG est absent alors qu'il est obligatoire.\n";
        exit(1);
    }

    echo "SUCCÈS : Les formats ont été correctement nettoyés.\n";
    exit(0);

} catch (\Throwable $t) {
    echo "Exception capturée : " . $t->getMessage() . "\n";
    exit(1);
}
