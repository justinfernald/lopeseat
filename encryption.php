<?php
require('api.php');
require('Ledger.php');

$ledger = new Ledger();
$ledger->write(34, 50.23, 3, 1);
$ledger->write(34, 78.62, 4, 2);
?>