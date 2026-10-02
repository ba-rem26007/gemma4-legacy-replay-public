<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #30314, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

// Create a Manufacturer to test the link generation
$m = new Manufacturer();
$m->name = 'Studio Design';
$m->active = 1;
if (!$m->add()) {
    echo "Failed to create manufacturer\n";
    exit(1);
}

$id_m = (int)$m->id;
echo "Testing Manufacturer ID: $id_m\n";

// The {url} helper in Smarty calls Link::getUrlSmarty
// We simulate the call: {url entity='manufacturer' id=1}
$params = [
    'entity' => 'manufacturer',
    'id' => $id_m,
];

try {
    $url_observed = Link::getUrlSmarty($params);
    
    // To get the expected URL, we must use a Manufacturer object fully loaded from the DB
    // to ensure the link_rewrite (alias) is populated, as getManufacturerLink depends on it.
    $m_loaded = new Manufacturer($id_m, (int)$context->language->id);
    
    // getUrlSmarty uses 'relative_protocol' => true by default
    $url_expected = $context->link->getManufacturerLink(
        $m_loaded, 
        null, 
        (int)$context->language->id, 
        (int)$context->shop->id, 
        true
    );

    echo "Observed URL: $url_observed\n";
    echo "Expected URL: $url_expected\n";

    // The bug is that it returns a non-rewritten URL (containing ?id_manufacturer=)
    if (strpos($url_observed, 'id_manufacturer=') !== false) {
        echo "FAILURE: The URL is not rewritten (contains id_manufacturer=).\n";
        exit(1);
    }

    if ($url_observed === $url_expected) {
        echo "SUCCESS: The URL is correctly rewritten and matches getManufacturerLink.\n";
        exit(0);
    } else {
        echo "FAILURE: The URL is rewritten but does not match the expected output.\n";
        exit(1);
    }
} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
