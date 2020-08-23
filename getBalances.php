<?php
require('api.php');
require('Ledger.php');

$user = $GLOBALS['user'];

if (!isLoggedIn()) {
    result(false, "Not logged in");
    exit();
}

$ledger = new Ledger();

echo json_encode(Array($ledger->getQuickBalance($user->id, 1), $ledger->getQuickBalance($user->id, 2)));
?>