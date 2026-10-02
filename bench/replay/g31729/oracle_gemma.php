<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #31729, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Test de non-régression pour le ticket :
 * "Incorrect condition disallows use of index date_add on table ps_connections"
 * 
 * Le bug réside dans l'utilisation de fonctions SQL (TIME_TO_SEC, TIMEDIFF) 
 * sur la colonne `date_add`, ce qui empêche MySQL d'utiliser l'index.
 */

// Simulation d'un contexte d'administration pour éviter les erreurs dans le constructeur de AdminController
$_GET['token'] = 'test_token';
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

// On s'assure qu'un employé est "connecté" pour le contrôleur
$employee = new Employee(1);
if (!Validate::isLoadedObject($employee)) {
    // Création minimale si absent
    $e = new Employee();
    $e->firstname = 'Test';
    $e->lastname = 'User';
    $e->email = 'test@example.com';
    $e->passwd = Tools::encrypt('password');
    $e->add();
    $employee = $e;
}
$context->employee = $employee;

try {
    // On instancie le contrôleur. Le constructeur définit les propriétés _select et _join.
    // On utilise AdminCartsController (qui hérite de AdminCartsControllerCore).
    $controller = new AdminCartsController();

    // Les propriétés _select et _join sont protégées dans AdminController.
    // On utilise la Reflection pour les inspecter.
    $reflector = new ReflectionClass($controller);
    
    $propSelect = $reflector->getProperty('_select');
    $propSelect->setAccessible(true);
    $select = $propSelect->getValue($controller);

    $propJoin = $reflector->getProperty('_join');
    $propJoin->setAccessible(true);
    $join = $propJoin->getValue($controller);

    echo "Analyse du SQL généré par AdminCartsController...\n";

    // 1. Vérification de la clause JOIN sur ps_connections (le cœur du problème de performance)
    // Avant : TIME_TO_SEC(TIMEDIFF('...', `date_add`)) < 1800
    // Après : `date_add` > date_add('...', interval -30 minute)
    $hasNonSargableJoin = strpos($join, 'TIME_TO_SEC(TIMEDIFF') !== false && strpos($join, '`date_add`') !== false;
    $hasSargableJoin = strpos($join, '`date_add` > date_add') !== false;

    echo "Join sargable : " . ($hasSargableJoin ? 'OUI' : 'NON') . "\n";
    echo "Join non-sargable (bug) : " . ($hasNonSargableJoin ? 'OUI' : 'NON') . "\n";

    // 2. Vérification de la clause SELECT pour les paniers abandonnés
    // Avant : TIME_TO_SEC(TIMEDIFF('...', a.`date_add`)) > 86400
    // Après : a.`date_add` > date_add('...', interval 1 day)
    $hasNonSargableSelect = strpos($select, 'TIME_TO_SEC(TIMEDIFF') !== false && strpos($select, 'a.`date_add`') !== false;
    $hasSargableSelect = strpos($select, 'a.`date_add` > date_add') !== false;

    echo "Select sargable : " . ($hasSargableSelect ? 'OUI' : 'NON') . "\n";
    echo "Select non-sargable (bug) : " . ($hasNonSargableSelect ? 'OUI' : 'NON') . "\n";

    // Le test échoue si on trouve encore des fonctions TIME_TO_SEC/TIMEDIFF sur les colonnes de date
    if ($hasNonSargableJoin || $hasNonSargableSelect) {
        echo "ÉCHEC : La requête utilise toujours des fonctions non-sargables sur date_add.\n";
        exit(1);
    }

    if ($hasSargableJoin && $hasSargableSelect) {
        echo "SUCCÈS : Les conditions ont été optimisées pour utiliser les index.\n";
        exit(0);
    }

    echo "ÉCHEC : Le format attendu du correctif n'a pas été détecté.\n";
    exit(1);

} catch (\Throwable $t) {
    echo "Erreur fatale lors de l'exécution du test : " . $t->getMessage() . "\n";
    exit(1);
}
