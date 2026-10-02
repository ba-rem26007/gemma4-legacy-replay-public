<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #31241, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * Test for ConfigurationTest::test_sessions()
 * 
 * The bug: The old implementation checked if the 'session.save_path' was writable.
 * In some environments (like Plesk or CLI), this check could fail even if sessions 
 * are functional or if the path is not explicitly set/writable in the expected way.
 * 
 * The fix: Check session_status() instead.
 */

// We simulate a non-writable session path to trigger the failure in the old code.
// Since we are in a container, we can set a path that definitely doesn't exist.
$invalidPath = '/tmp/non_existent_path_prestashop_test_' . uniqid();
ini_set('session.save_path', $invalidPath);

echo "Simulating invalid session.save_path: $invalidPath\n";

try {
    $result = ConfigurationTest::test_sessions();
    echo "ConfigurationTest::test_sessions() returned: " . ($result ? 'true' : 'false') . "\n";
} catch (\Throwable $e) {
    echo "An exception occurred: " . $e->getMessage() . "\n";
    exit(1);
}

// Before fix: is_writable($invalidPath) is false -> returns false -> exit(1)
// After fix: session_status() is PHP_SESSION_NONE (usually) -> returns true -> exit(0)
if ($result === true) {
    echo "SUCCESS: Session test passed (correctif applied).\n";
    exit(0);
} else {
    echo "FAILURE: Session test failed (old code still present).\n";
    exit(1);
}
