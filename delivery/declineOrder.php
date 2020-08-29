<?php
require_once('../api.php');
require('../delivery/deliveryQueue.php');

$orderId = $_GET['order'];$user = $GLOBALS['user'];

if ($user == null) {
    result(false, "Not logged into a delivery account");
    exit();
}

if ($user->deliverer == 0) {
    result(false, "Not on a delivery account");
    exit();
}

if (acceptDelivererRequest($deliverId, $orderId)) {
    result(true);
    exit();
}

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    result(false, "Invalid ID");
    exit();
}

$orderId = (int)$_GET['id'];

$delivererId = $user->id;

if (declineDelivererRequest($delivererId, $orderId)) {
    result(true);
    exit();
}

result(false, "Order can't be declined");
?>