<?php
require('api.php');
require('Ledger.php');

$ledger = new Ledger();
$balance = $ledger->getQuickBalance($GLOBALS['user']);
echo $balance;
?>