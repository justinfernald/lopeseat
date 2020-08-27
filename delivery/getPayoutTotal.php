<?php
require('../api.php');
require("../ledger/Ledger.php");

$user = $GLOBALS['user'];

if ($user == null || $user->deliverer == 0) {
    result(false, "Not logged into delivery account.");
    exit();
}

$ledger = new Ledger();
echo json_encode(array("total"=>$ledger->getQuickBalance($user->id, 2)));
?>