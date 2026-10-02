<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #32535, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);
$context->currency = new Currency(1);

try {
    // 1. Prepare a carrier with an EMPTY tracking URL
    $carrier = new Carrier(1);
    $carrier->url = ''; 
    $carrier->save();
    echo "Carrier 1 URL set to empty\n";

    // 2. Use an existing order from demo data
    $order = new Order(1);
    if (!Validate::isLoadedObject($order)) {
        echo "Order 1 not found in demo data\n";
        exit(1);
    }
    
    // Ensure the order is linked to the carrier we just modified
    $order->id_carrier = $carrier->id;
    $order->save();
    echo "Order 1 linked to Carrier 1\n";

    // 3. Create/Update OrderCarrier associated with this order
    // We must set id_order and id_carrier as they are required by the ObjectModel definition
    $oc = new OrderCarrier();
    $oc->id_order = (int)$order->id;
    $oc->id_carrier = (int)$carrier->id;
    $oc->save();
    echo "OrderCarrier created for Order 1\n";

    // The method sendInTransitEmail should return true immediately if the carrier URL is empty.
    // If the fix is NOT present, it will proceed to call Mail::Send().
    // In a CLI environment without SMTP configured, Mail::Send() typically returns false.
    $result = $oc->sendInTransitEmail($order);
    
    echo "sendInTransitEmail return value: " . var_export($result, true) . "\n";

    // If result is true, the fix worked (it returned early).
    // If result is false, it tried to send the email and failed (fix missing).
    exit($result === true ? 0 : 1);

} catch (\Throwable $e) {
    echo "Exception caught: " . $e->getMessage() . "\n";
    exit(1);
}
