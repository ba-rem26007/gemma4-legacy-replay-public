<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #32840, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup: Create a hook to ensure we have data to cache
$hookName = 'test_cache_bug_hook_' . uniqid();
$hook = new Hook();
$hook->name = $hookName;
$hook->title = 'Test Cache Bug Hook';
$hook->add();

// The bug is in Hook::getAllHookIds, which is called by Hook::getIdByName.
// We pass true for $withAliases and true for $refreshCache to force the Cache::store() call.
Hook::getIdByName($hookName, true, true);

// The bug is that the cache key is concatenated incorrectly:
// 'hook_idsbyname' . 'hook_idsbyname_withalias' => 'hook_idsbynamehook_idsbyname_withalias'
// instead of being assigned: 'hook_idsbyname_withalias'

$correctKey = 'hook_idsbyname_withalias';
$wrongKey = 'hook_idsbynamehook_idsbyname_withalias';

$isCorrectKeyStored = Cache::isStored($correctKey);
$isWrongKeyStored = Cache::isStored($wrongKey);

echo "Correct key ('$correctKey') stored: " . ($isCorrectKeyStored ? 'YES' : 'NO') . "\n";
echo "Wrong key ('$wrongKey') stored: " . ($isWrongKeyStored ? 'YES' : 'NO') . "\n";

// The test passes if the correct key is used and the wrong one is not.
if ($isCorrectKeyStored && !$isWrongKeyStored) {
    exit(0);
} else {
    exit(1);
}
