<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #38341, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

// Use existing Order 1 to find a carrier that is "used"
$order = new Order(1);
if (!Validate::isLoadedObject($order)) {
    echo "Order 1 not found in demo data\n";
    exit(1);
}

$carrierId = (int)$order->id_carrier;
$carrier = new Carrier($carrierId);

if (!Validate::isLoadedObject($carrier)) {
    echo "Carrier $carrierId not found\n";
    exit(1);
}

echo "Testing deletion of Carrier ID: $carrierId (used by Order 1)\n";

// Ensure the carrier is not already marked as deleted before the test
$carrier->deleted = 0;
$carrier->update();

// Trigger the logic: delete the carrier
// Before fix: calls parent::delete() -> hard delete
// After fix: calls parent::softDelete() because isUsed() is true -> deleted = 1
try {
    $carrier->delete();
} catch (\Throwable $e) {
    echo "Exception during delete: " . $e->getMessage() . "\n";
    exit(1);
}

// Check if the carrier still exists in the database
$sql = 'SELECT deleted FROM ' . _DB_PREFIX_ . 'carrier WHERE id_carrier = ' . (int)$carrierId;
$result = Db::getInstance()->getRow($sql);

if ($result === false) {
    echo "Result: Carrier was HARD deleted from the database. (BUG)\n";
    exit(1);
}

if ((int)$result['deleted'] === 1) {
    echo "Result: Carrier was SOFT deleted (deleted = 1). (FIXED)\n";
    exit(0);
}

echo "Result: Carrier still exists but 'deleted' flag is " . $result['deleted'] . " (Unexpected state)\n";
exit(1);
