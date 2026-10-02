<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37009, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

try {
    $moduleName = 'hooktest';
    $moduleDir = _PS_MODULE_DIR_ . $moduleName . '/';
    $moduleFile = $moduleDir . $moduleName . '.php';
    $paramsFile = '/tmp/ps_hook_params.txt';

    // 1. Create a physical module file
    // PrestaShop's Module::getInstanceByName() requires the file to exist and the class to be defined within it.
    if (!is_dir($moduleDir)) {
        mkdir($moduleDir, 0777, true);
    }
    
    $moduleContent = '<?php
    class HookTest extends Module {
        public function __construct() {
            $this->name = "' . $moduleName . '";
            $this->tab = "administration";
            $this->version = "1.0";
            $this->author = "Test";
            parent::__construct();
        }
        public function hookActionEmailSendBefore($params) {
            file_put_contents("' . $paramsFile . '", serialize($params));
        }
    }';
    file_put_contents($moduleFile, $moduleContent);

    // 2. Register the module in the database
    Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'module WHERE name = "' . pSQL($moduleName) . '"');
    Db::getInstance()->execute('INSERT INTO ' . _DB_PREFIX_ . 'module (name, active) VALUES ("' . pSQL($moduleName) . '", 1)');
    
    // Register the module to the hook
    Hook::registerHook('actionEmailSendBefore', $moduleName);

    // 3. Disable actual mail sending
    Configuration::updateValue('PS_MAIL_METHOD', Mail::METHOD_DISABLE);

    // 4. Create a dummy template file
    // When passing _PS_MAIL_DIR_ as the template path, Mail::send looks for the file directly in that directory.
    if (!is_dir(_PS_MAIL_DIR_)) {
        mkdir(_PS_MAIL_DIR_, 0777, true);
    }
    file_put_contents(_PS_MAIL_DIR_ . 'contact.html', '<html><body>Test</body></html>');
    file_put_contents(_PS_MAIL_DIR_ . 'contact.txt', 'Test');

    // 5. Define test data
    $idLang = 1;
    $template = 'contact';
    $subject = 'Test Subject';
    $templateVars = [];
    $to = 'recipient@example.com';
    $toName = 'Recipient Name';
    $from = 'sender@example.com';
    $fromName = 'Sender Name';
    $replyTo = 'reply@example.com';
    $replyToName = 'Reply To Name Special';

    // 6. Trigger Mail::send()
    // Signature: send($idLang, $template, $subject, $templateVars, $to, $toName, $from, $fromName, $fileAttachment, $modeSend, $templatePath, $bcc, $replyTo, $replyToName)
    Mail::send(
        $idLang,
        $template,
        $subject,
        $templateVars,
        $to,
        $toName,
        $from,
        $fromName,
        null,
        Mail::METHOD_DISABLE,
        _PS_MAIL_DIR_,
        null,
        $replyTo,
        $replyToName
    );

    // 7. Verify if the hook captured the parameters
    if (!file_exists($paramsFile)) {
        echo "FAILURE: Hook was not triggered (params file not created).\n";
        // Cleanup
        @unlink($moduleFile);
        @rmdir($moduleDir);
        exit(1);
    }

    $params = unserialize(file_get_contents($paramsFile));
    unlink($paramsFile);

    echo "Hook parameters captured:\n";
    echo "replyTo: " . ($params['replyTo'] ?? 'NOT FOUND') . "\n";
    echo "replyToName: " . ($params['replyToName'] ?? 'NOT FOUND') . "\n";

    if (isset($params['replyToName']) && $params['replyToName'] === $replyToName) {
        echo "SUCCESS: replyToName is present and correct.\n";
        @unlink($moduleFile);
        @rmdir($moduleDir);
        exit(0);
    } else {
        echo "FAILURE: replyToName is missing or incorrect.\n";
        @unlink($moduleFile);
        @rmdir($moduleDir);
        exit(1);
    }

} catch (\Throwable $t) {
    echo "ERROR: " . $t->getMessage() . "\n";
    exit(1);
}
