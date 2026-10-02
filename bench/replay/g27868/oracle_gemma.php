<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #27868, validé pre/post automatiquement
require 'config/config.inc.php';

// Context setup
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

try {
    // Use existing OrderInvoice or create one linked to Order 1
    $orderInvoice = new OrderInvoice(1);
    if (!Validate::isLoadedObject($orderInvoice)) {
        $orderInvoice = new OrderInvoice();
        $orderInvoice->id_order = 1;
        $orderInvoice->number = 1;
        $orderInvoice->delivery_number = 1;
        $orderInvoice->date_add = '2022-03-03 10:00:00';
        $orderInvoice->add();
    }

    // Set specific dates to trigger the bug
    // Invoice created on March 3rd, delivered on March 4th
    $dateAdd = '2022-03-03 10:00:00';
    $dateDelivery = '2022-03-04 10:00:00';
    
    $orderInvoice->date_add = $dateAdd;
    $orderInvoice->delivery_date = $dateDelivery;
    $orderInvoice->update();

    // Instantiate dependencies
    $smarty = new Smarty();
    
    // Instantiate the class under test
    // HTMLTemplateDeliverySlip is the class used by PrestaShop (extending HTMLTemplateDeliverySlipCore)
    $template = new HTMLTemplateDeliverySlip($orderInvoice, $smarty);

    $expectedDate = Tools::displayDate($dateDelivery);
    $observedDate = $template->date;

    echo "Date de facture (date_add) : " . Tools::displayDate($dateAdd) . "\n";
    echo "Date de livraison (delivery_date) : $expectedDate\n";
    echo "Date observée dans le template : $observedDate\n";

    // The test passes if the template uses the delivery_date
    if ($observedDate === $expectedDate) {
        exit(0);
    } else {
        echo "ERREUR : Le template utilise la date de facture au lieu de la date de livraison.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Exception : " . $t->getMessage() . "\n";
    exit(1);
}
