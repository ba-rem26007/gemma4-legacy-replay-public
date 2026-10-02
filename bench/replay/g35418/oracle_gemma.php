<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #35418, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Core\Security\PasswordPolicyConfiguration;

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1); // French
$context->currency = new Currency(1);

// Use demo customer 1
$customer = new Customer(1);
if (!Validate::isLoadedObject($customer)) {
    $customer = new Customer();
    $customer->firstname = 'Test';
    $customer->lastname = 'User';
    $customer->email = 'test@example.com';
    $customer->passwd = Tools::encrypt('password123');
    $customer->active = 1;
    $customer->add();
}
$customer->active = 1;
$customer->save();

// Configure a strict password policy to trigger the bug
// Min length 8, Min score 2 (casetta is length 7 and score 0/1)
Configuration::updateValue(PasswordPolicyConfiguration::CONFIGURATION_MINIMUM_LENGTH, 8);
Configuration::updateValue(PasswordPolicyConfiguration::CONFIGURATION_MAXIMUM_LENGTH, 32);
Configuration::updateValue(PasswordPolicyConfiguration::CONFIGURATION_MINIMUM_SCORE, 2);

// Simulate the password reset form submission
$_POST = [
    'id_customer' => (int)$customer->id,
    'token' => $customer->secure_key,
    'passwd' => 'casetta', // Weak password: too short and low score
    'passwd_confirm' => 'casetta',
    'submitPassword' => 1,
];

// Instantiate the controller
$controller = new PasswordController();

try {
    // postProcess() calls changePassword() if token and id_customer are present
    $controller->postProcess();
    
    $errors = $controller->errors;
    $translator = Context::getContext()->getTranslator();

    // Generate expected translated strings using the context translator
    $expectedLengthError = $translator->trans(
        'Password must be between %d and %d characters long',
        [
            (int)Configuration::get(PasswordPolicyConfiguration::CONFIGURATION_MINIMUM_LENGTH),
            (int)Configuration::get(PasswordPolicyConfiguration::CONFIGURATION_MAXIMUM_LENGTH),
        ],
        'Shop.Notifications.Error'
    );

    // For score 2, the wording is 'Average'
    $scoreWording = $translator->trans('Average', [], 'Shop.Theme.Global');
    $expectedScoreError = $translator->trans(
        'The minimum score must be: %s',
        [$scoreWording],
        'Shop.Notifications.Error'
    );

    $hasLengthError = false;
    $hasScoreError = false;

    foreach ($errors as $error) {
        if ($error === $expectedLengthError) {
            $hasLengthError = true;
        }
        if ($error === $expectedScoreError) {
            $hasScoreError = true;
        }
    }

    echo "Password used: casetta\n";
    echo "Errors found: " . count($errors) . "\n";
    echo "Length error detected: " . ($hasLengthError ? 'YES' : 'NO') . "\n";
    echo "Score error detected: " . ($hasScoreError ? 'YES' : 'NO') . "\n";

    // The test passes if BOTH policy checks are triggered for the weak password
    if ($hasLengthError && $hasScoreError) {
        exit(0);
    } else {
        echo "Bug still present: Password policy was not enforced.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Fatal error: " . $t->getMessage() . "\n";
    exit(1);
}
