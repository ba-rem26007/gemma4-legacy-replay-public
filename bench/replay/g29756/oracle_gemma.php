<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29756, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

try {
    // 1. Verify the constant HTTP_PATCH exists in WebserviceRequest
    if (!defined('WebserviceRequest::HTTP_PATCH')) {
        echo "FAILED: WebserviceRequest::HTTP_PATCH constant is not defined.\n";
        exit(1);
    }
    $patchConst = WebserviceRequest::HTTP_PATCH;
    echo "WebserviceRequest::HTTP_PATCH is defined: $patchConst\n";

    // 2. Verify Currency webservice parameters
    $currency = new Currency(1);
    if (!Validate::isLoadedObject($currency)) {
        $currency = new Currency();
        $currency->iso_code = 'USD';
        $currency->conversion_rate = 1.0;
        $currency->add();
    }

    $params = $currency->getWebserviceParameters();

    if (!$params || !isset($params['fields'])) {
        echo "FAILED: getWebserviceParameters() did not return the expected 'fields' array.\n";
        exit(1);
    }

    $fields = $params['fields'];

    // Check 'name' field
    if (!isset($fields['name']['modifier']['http_method'])) {
        echo "FAILED: Structure for 'name' modifier is missing in fields.\n";
        exit(1);
    }
    $nameMethod = $fields['name']['modifier']['http_method'];

    // Check 'iso_code' field
    if (!isset($fields['iso_code']['modifier']['http_method'])) {
        echo "FAILED: Structure for 'iso_code' modifier is missing in fields.\n";
        exit(1);
    }
    $isoMethod = $fields['iso_code']['modifier']['http_method'];

    echo "Currency name allowed methods bitmask: $nameMethod\n";
    echo "Currency iso_code allowed methods bitmask: $isoMethod\n";

    // The fix adds WebserviceRequest::HTTP_PATCH to the bitmask.
    $nameAllowsPatch = ($nameMethod & $patchConst) === $patchConst;
    $isoAllowsPatch = ($isoMethod & $patchConst) === $patchConst;

    if ($nameAllowsPatch && $isoAllowsPatch) {
        echo "SUCCESS: Currency fields allow PATCH requests.\n";
        exit(0);
    } else {
        echo "FAILED: Currency fields do not allow PATCH requests.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "EXCEPTION: " . $t->getMessage() . "\n";
    exit(1);
}
