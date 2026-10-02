<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #26788, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

try {
    // Use existing Cart 1 or create a minimal one
    $cart = new Cart(1);
    if (!Validate::isLoadedObject($cart)) {
        $cart = new Cart();
        $cart->id_currency = 1;
        $cart->id_lang = 1;
        $cart->add();
    }

    // Ensure id_lang is set for the test
    $cart->id_lang = 1;

    // First call to getAssociatedLanguage()
    $lang1 = $cart->getAssociatedLanguage();
    
    // Second call to getAssociatedLanguage()
    $lang2 = $cart->getAssociatedLanguage();

    echo "Language 1 ID: " . $lang1->id . "\n";
    echo "Language 2 ID: " . $lang2->id . "\n";

    // Before the fix: getAssociatedLanguage() returns a NEW instance of Language every time.
    // After the fix: it returns the cached instance stored in $this->lang_associated.
    $isSameInstance = ($lang1 === $lang2);

    echo "Same instance: " . ($isSameInstance ? 'YES' : 'NO') . "\n";

    if ($isSameInstance) {
        exit(0); // Fixed: the same object is returned
    } else {
        exit(1); // Bug: a new object is instantiated every time
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
