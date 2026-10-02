<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #30511, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\Meta\HtaccessFileGenerator;
use PrestaShop\PrestaShop\Adapter\Translator\Translator as AdapterTranslator;
use PrestaShop\PrestaShop\Adapter\Feature\MultistoreFeature;
use PrestaShop\PrestaShop\Adapter\Shop\Context as AdapterShopContext;
use PrestaShop\PrestaShop\Adapter\Meta\SetUpUrlsDataConfiguration;

try {
    // 1. Initial state: Friendly URLs are DISABLED in the database
    Configuration::updateValue('PS_REWRITING_SETTINGS', 0);
    
    // Ensure .htaccess is clean to avoid false positives
    if (file_exists('.htaccess')) {
        unlink('.htaccess');
    }

    // 2. Instantiate dependencies
    // SetUpUrlsDataConfiguration expects:
    // Configuration (legacy), Context (Adapter), FeatureInterface (Adapter), HtaccessFileGenerator, TranslatorInterface
    $configuration = new Configuration(); 
    $shopContext = new AdapterShopContext();
    
    // MultistoreFeature requires the Adapter Shop Context in its constructor
    $multistoreFeature = new MultistoreFeature($shopContext);
    
    $htaccessFileGenerator = new HtaccessFileGenerator();
    $translator = new AdapterTranslator();

    $setup = new SetUpUrlsDataConfiguration(
        $configuration,
        $shopContext,
        $multistoreFeature,
        $htaccessFileGenerator,
        $translator
    );

    // 3. Trigger the update to ENABLE Friendly URLs
    $config = [
        'friendly_url' => 1,
        'accented_url' => 1,
        'canonical_url_redirection' => 1,
        'disable_apache_multiview' => 0,
        'disable_apache_mod_security' => 0,
    ];

    echo "Updating configuration to enable friendly_url...\n";
    $setup->updateConfiguration($config);

    // 4. Verification
    // Before fix: .htaccess is generated BEFORE PS_REWRITING_SETTINGS is updated in DB.
    // The generator reads the OLD value (0) and creates a non-friendly .htaccess.
    // After fix: PS_REWRITING_SETTINGS is updated to 1 FIRST, then .htaccess is generated.
    
    $dbValue = Configuration::get('PS_REWRITING_SETTINGS');
    $htaccessContent = '';
    if (file_exists('.htaccess')) {
        $htaccessContent = file_get_contents('.htaccess');
    }

    echo "Value in DB: $dbValue\n";
    
    // Check if .htaccess contains RewriteRules (indicates friendly URLs are active)
    $hasFriendlyRules = (strpos($htaccessContent, 'RewriteRule') !== false);
    echo "Htaccess contains RewriteRules: " . ($hasFriendlyRules ? 'Yes' : 'No') . "\n";

    if ($dbValue == 1 && $hasFriendlyRules) {
        echo "SUCCESS: .htaccess reflects the updated configuration.\n";
        exit(0);
    } else {
        echo "FAILURE: .htaccess does not reflect the updated configuration (Bug present).\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
