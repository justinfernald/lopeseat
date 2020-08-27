<?php
require('../api.php');

$user = $GLOBALS['user'];

if ($user == null || $user->deliverer == 0) {
    result(false, "Not logged into a delivery account.");
    exit();
}

$payoutId = $_GET['payoutId'];

if (!isset($payoutId)) {
    result(false, "No balance update id given.");
    exit();
}

$result = getPayoutStatus();

if ($results == null) {
    result(false, "No payout found");
    exit();
}

echo json_encode(array("status"=>$result));
?>