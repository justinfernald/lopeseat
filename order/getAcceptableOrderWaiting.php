<?php
require '../api.php';
require '../delivery/deliveryQueue.php';

$user = $GLOBALS['user'];

if ($user == null) {
    result(false, "Not logged into a delivery account");
    exit();
}

if ($user->deliverer == 0) {
    result(false, "Not on a delivery account");
    exit();
}

$delivererId = $user->id;

$orders = getAcceptableOrders($delivererId);
result(true, $orders);
