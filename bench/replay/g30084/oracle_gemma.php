<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #30084, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Test for Checkout - PHP Error in Shipping step
 * The bug occurs when no shipping methods are available, causing $selectedDeliveryOption to be 0.
 * The code then attempts to unset an array key on an integer: unset(0['product_list']).
 */

// 1. Define dummy classes to satisfy type hints in CheckoutPaymentStep constructor
// Since the real classes were not found by the autoloader in the previous attempt,
// we define them in the global namespace as expected by the legacy class.
if (!class_exists('PaymentOptionsFinder')) {
    class PaymentOptionsFinder {
        public function present($isFree) { return []; }
    }
}
if (!class_exists('ConditionsToApproveFinder')) {
    class ConditionsToApproveFinder {
        public function getConditionsToApproveForTemplate() { return []; }
    }
}

// 2. Setup Context and Data
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

$cart = new Cart();
$cart->id_currency = 1;
$cart->id_lang = 1;
$cart->add();

// 3. Mock Translator
class MockTranslator implements \Symfony\Contracts\Translation\TranslatorInterface {
    public function trans($id, array $parameters = [], $domain = null, $locale = null) { return $id; }
    public function getLocale() { return 'fr'; }
    public function setLocale($locale) {}
    public function setKernel($kernel) {}
}
$translator = new MockTranslator();

$paymentOptionsFinder = new PaymentOptionsFinder();
$conditionsToApproveFinder = new ConditionsToApproveFinder();

// 4. Mock the Step to control the CheckoutSession and avoid template rendering
class TestCheckoutPaymentStep extends CheckoutPaymentStep {
    private $cart;
    public function setTestCart($cart) { $this->cart = $cart; }
    
    public function getCheckoutSession() {
        return new class($this->cart) {
            private $cart;
            public function __construct($cart) { $this->cart = $cart; }
            public function getCart() { return $this->cart; }
            public function isRecyclable() { return false; }
            
            // Trigger bug: return empty options but a non-empty selected key
            // This forces $selectedDeliveryOption = 0 in render()
            public function getDeliveryOptions() { return []; }
            public function getSelectedDeliveryOption() { return 'none_available'; }
        };
    }

    // Override renderTemplate to avoid Smarty errors in CLI
    public function renderTemplate($template, $extraParams, $assignedVars) {
        return 'rendered_template';
    }
}

// 5. Error Handling to detect the PHP Warning/Error
$bugTriggered = false;
set_error_handler(function($errno, $errstr) use (&$bugTriggered) {
    // The bug is "Trying to access array offset on value of type int" (PHP 8) 
    // or "Illegal string offset" / "Notice" (PHP 7)
    if (strpos($errstr, 'array offset') !== false || strpos($errstr, 'offset') !== false || strpos($errstr, 'unset') !== false) {
        $bugTriggered = true;
    }
    return true;
});

try {
    $step = new TestCheckoutPaymentStep($context, $translator, $paymentOptionsFinder, $conditionsToApproveFinder);
    $step->setTestCart($cart);
    
    echo "Calling render()...\n";
    $step->render();
} catch (\Throwable $t) {
    echo "Fatal error or exception caught: " . $t->getMessage() . "\n";
    $bugTriggered = true;
}

restore_error_handler();

echo "Bug triggered (PHP Error observed): " . ($bugTriggered ? 'YES' : 'NO') . "\n";

// exit(0) if behavior is CORRECTED (no bug triggered), exit(1) if bug still exists
exit($bugTriggered ? 1 : 0);
