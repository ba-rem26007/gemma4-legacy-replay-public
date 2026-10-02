<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37970, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

// Enable HTMLPurifier to trigger the code path in Tools::purifyHTML
Configuration::updateValue('PS_USE_HTMLPURIFIER', 1);

$htmlInput = '<video autoplay="autoplay" loop="loop" muted="muted" playsinline="playsinline"><source src="VIDEO_URL" type="video/mp4"></video>';

echo "Input HTML: $htmlInput\n";

try {
    $purifiedHtml = Tools::purifyHTML($htmlInput);
    echo "Purified HTML: $purifiedHtml\n";

    $requiredAttributes = ['autoplay', 'loop', 'muted', 'playsinline'];
    $missing = [];

    foreach ($requiredAttributes as $attr) {
        if (strpos($purifiedHtml, $attr) === false) {
            $missing[] = $attr;
        }
    }

    if (empty($missing)) {
        echo "All video attributes preserved.\n";
        exit(0);
    } else {
        echo "Missing attributes: " . implode(', ', $missing) . "\n";
        exit(1);
    }
} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
