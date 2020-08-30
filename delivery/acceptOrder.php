<?php
require_once '../api.php';
require '../delivery/deliveryQueue.php';

$orderId = $_GET['order'];
$user = $GLOBALS['user'];

if ($user == null) {
    result(false, "Not logged into a delivery account");
    exit();
}

if ($user->deliverer == 0) {
    result(false, "Not on a delivery account");
    exit();
}

if (!isset($_GET['order']) || !ctype_digit($_GET['order'])) {
    result(false, "Invalid ID");
    exit();
}

$orderId = (int) $_GET['order'];

$delivererId = $user->id;

// if (!isOrderAcceptable($delivererId, $orderId)) {
//     result(false, "that is just not acceptable");
//     exit();
// }

if (acceptDelivererRequest($delivererId, $orderId)) {
    result(true);
    exit();
}

result(false, "Order can't be accepted");
