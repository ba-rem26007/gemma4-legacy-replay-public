<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29944, validé pre/post automatiquement
require 'config/config.inc.php';

// Ensure essential constants are defined for the logic in QuickAccess and Link
if (!defined('_PS_ADMIN_DIR_')) {
    define('_PS_ADMIN_DIR_', 'admin/');
}
if (!defined('_PS_BASE_URL_')) {
    define('_PS_BASE_URL_', 'http://localhost/');
}
if (!defined('_PS_BASE_URI_')) {
    define('_PS_BASE_URI_', '/');
}

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

// We mock the Link class using an anonymous class to bypass the Symfony container 
// which is not available in CLI and causes "Call to a member function get() on null"
$context->link = new class extends Link {
    public function getQuickLink($link) {
        return $link;
    }
    public function getAdminBaseLink() {
        // Returns a relative path to simulate the FIX
        return '/admin/';
    }
    public function getBaseLink() {
        // Returns an absolute URL to simulate the BUG (pre-fix behavior)
        return 'http://localhost/admin/';
    }
};

// Set shop context
Shop::setContext(Shop::CONTEXT_SHOP);

// Setup Employee
$employee = new Employee(1);
if (!Validate::isLoadedObject($employee)) {
    $employee = new Employee();
    $employee->firstname = 'Test';
    $employee->lastname = 'Test';
    $employee->email = 'test@test.com';
    $employee->passwd = 'password';
    $employee->add();
}
$idEmployee = $employee->id;

// Setup QuickAccess entry
// We use a controller link to trigger the logic that uses getAdminBaseLink() / getBaseLink()
$qa = new QuickAccess();
$qa->link = 'index.php?controller=AdminProducts';
$qa->new_window = 0;
$qa->name = [1 => 'Products'];
$qa->add();

try {
    // Call the method targeted by the fix
    $results = QuickAccess::getQuickAccessesWithToken(1, $idEmployee);

    if (!$results) {
        echo "Error: No QuickAccess entries returned.\n";
        exit(1);
    }

    // Find our specific link in the results
    $foundLink = null;
    foreach ($results as $item) {
        if (strpos($item['link'], 'controller=AdminProducts') !== false) {
            $foundLink = $item['link'];
            break;
        }
    }

    if (!$foundLink) {
        echo "Error: The created QuickAccess link was not found in the results.\n";
        exit(1);
    }

    echo "Observed link: $foundLink\n";

    /**
     * BUG: The link contains the full domain (e.g., http://localhost/admin/...)
     * FIX: The link should be relative (e.g., /admin/...)
     * 
     * Before fix: uses getBaseLink() -> returns absolute URL
     * After fix: uses getAdminBaseLink() -> returns relative path
     */
    if (preg_match('/^https?:\/\//', $foundLink)) {
        echo "FAIL: Link contains the domain, which can cause logout in multishop.\n";
        exit(1);
    }

    if (strpos($foundLink, '/') === 0) {
        echo "SUCCESS: Link is relative.\n";
        exit(0);
    }

    echo "FAIL: Link is neither absolute nor relative starting with /: $foundLink\n";
    exit(1);

} catch (\Throwable $t) {
    echo "Exception caught: " . $t->getMessage() . "\n";
    exit(1);
}
