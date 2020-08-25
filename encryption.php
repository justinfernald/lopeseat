<?php
require('api.php');
require('Ledger.php');

echo openssl_encrypt("[]", 'aes-256-cbc', $GLOBALS['key'], 0, $GLOBALS['iv']);

$ledger = new Ledger();
$ledger->transferDeliveryEarnings(29, 100);
$ledger->transferDeliveryFeeFromLEB(29, 10);
$ledger->transferDeliveryFeeFromDB(29, 20);
?>