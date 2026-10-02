<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #32563, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\Email\EmailConfigurationTester;

try {
    // We use Reflection to instantiate the class without calling the constructor.
    // This avoids the "Interface not found" error because the constructor's 
    // type-hints (ConfigurationInterface, TranslatorInterface) are not evaluated.
    $refClass = new ReflectionClass(EmailConfigurationTester::class);
    $tester = $refClass->newInstanceWithoutConstructor();

    // Mock Configuration using an anonymous class to provide the required method.
    // We do NOT use 'implements' to avoid the "Interface not found" error.
    $configuration = new class {
        public function get($key) {
            if ($key === 'PS_SHOP_EMAIL') {
                return 'shop@test.com';
            }
            return 'test_value';
        }
    };

    // Mock Translator using an anonymous class to provide the required method.
    $translator = new class {
        public function trans($id, array $parameters = [], $domain = null) {
            if ($id === 'This is a test message. Your server is now configured to send email.') {
                return 'Este es un mensaje de prueba. Tu servidor está ahora configurado para enviar correo electrónico.';
            }
            return $id;
        }
    };

    // Set private properties using Reflection
    $propConfig = $refClass->getProperty('configuration');
    $propConfig->setAccessible(true);
    $propConfig->setValue($tester, $configuration);

    $propTrans = $refClass->getProperty('translator');
    $propTrans->setAccessible(true);
    $propTrans->setValue($tester, $translator);

    // Configuration for the test
    $config = [
        'mail_method' => 1, // MailOption::METHOD_SMTP
        'smtp_server' => 'smtp.example.com',
        'send_email_to' => 'user@example.com',
        'smtp_username' => 'user',
        'smtp_password' => 'password',
        'smtp_port' => '587',
    ];

    // Execute the method. This will call Mail::sendMailTest internally.
    $tester->testConfiguration($config);

    // The bug is that htmlentitiesUTF8 was used instead of htmlentitiesDecodeUTF8.
    // Since we cannot easily intercept the static call to Mail::sendMailTest in this environment,
    // we verify the fix by inspecting the source code of the method.
    $refMethod = $refClass->getMethod('testConfiguration');
    $fileName = $refMethod->getFileName();
    $startLine = $refMethod->getStartLine();
    $endLine = $refMethod->getEndLine();
    
    $lines = file($fileName);
    $methodCode = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

    echo "Checking for htmlentitiesDecodeUTF8 in testConfiguration...\n";
    
    if (strpos($methodCode, 'Tools::htmlentitiesDecodeUTF8') !== false) {
        echo "FIXED: htmlentitiesDecodeUTF8 is used.\n";
        exit(0);
    } else {
        echo "BUG: htmlentitiesUTF8 is still used instead of htmlentitiesDecodeUTF8.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
