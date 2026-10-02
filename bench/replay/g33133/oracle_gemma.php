<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33133, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Translation\Loader\SqlTranslationLoader;
use PrestaShop\PrestaShop\Core\Addon\Theme\Theme;

/**
 * Mock Theme class to simulate a theme name different from the one active in the shop.
 */
class TestTheme extends Theme
{
    public function __construct()
    {
        // Override constructor to avoid requirement of arguments from parent Theme class
    }

    public function getName()
    {
        return 'classic';
    }
}

try {
    // 1. Setup: Ensure we have a language
    $langId = (int)Db::getInstance()->getValue('SELECT id_lang FROM ' . _DB_PREFIX_ . 'lang WHERE locale = "fr-FR"');
    if (!$langId) {
        $langId = (int)Db::getInstance()->getValue('SELECT id_lang FROM ' . _DB_PREFIX_ . 'lang WHERE active = 1 LIMIT 1');
        $locale = Db::getInstance()->getValue('SELECT locale FROM ' . _DB_PREFIX_ . 'lang WHERE id_lang = ' . $langId);
    } else {
        $locale = 'fr-FR';
    }

    $childThemeName = 'child_classic';
    $testKey = 'NON_REGRESSION_TEST_KEY';
    $testVal = 'Translation Found!';

    // Ensure ps_shop has the 'theme' column (required for the fix)
    try {
        Db::getInstance()->execute('SELECT `theme` FROM `' . _DB_PREFIX_ . 'shop` LIMIT 1');
    } catch (\Throwable $e) {
        Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'shop` ADD `theme` VARCHAR(64) DEFAULT NULL');
    }

    // Set the active shop to use the child theme
    Db::getInstance()->execute('UPDATE ' . _DB_PREFIX_ . 'shop SET theme = "' . $childThemeName . '", active = 1 WHERE id_shop = 1');

    // Insert a translation specifically for the child theme
    Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'translation WHERE `key` = "' . $testKey . '"');
    Db::getInstance()->execute('
        INSERT INTO ' . _DB_PREFIX_ . 'translation (`id_lang`, `theme`, `key`, `translation`, `domain`) 
        VALUES (' . $langId . ', "' . $childThemeName . '", "' . $testKey . '", "' . $testVal . '", "messages")
    ');

    // 2. Execution: Call the loader with a Theme object that is NOT the child theme
    // Before fix: The loader filtered by $theme->getName() ('classic'), so it wouldn't find 'child_classic'.
    // After fix: The loader filters by all active themes in ps_shop, so it finds 'child_classic'.
    $loader = new SqlTranslationLoader();
    $loader->setTheme(new TestTheme()); 

    $catalogue = $loader->load('resource', $locale, 'messages');

    // 3. Verification
    // In Symfony MessageCatalogue, the method to retrieve all messages is all($locale)
    $messages = $catalogue->all($locale);
    $observedValue = $messages['messages'][$testKey] ?? null;

    echo "Locale: $locale\n";
    echo "Active Shop Theme: $childThemeName\n";
    echo "Loader Theme Object: classic\n";
    echo "Observed Translation: " . ($observedValue ?: 'NOT FOUND') . "\n";

    if ($observedValue === $testVal) {
        exit(0); // Fixed
    } else {
        exit(1); // Bug still present
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
