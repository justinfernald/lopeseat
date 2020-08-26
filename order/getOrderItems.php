<?php
include('../api.php');

$user = $GLOBALS['user'];
$order = $_GET['id'];

if ($user == null) {
    result(false, "Not logged in");
    exit();
}

if ($user->deliverer == 0) {
    result(false, "Not on a delivery account");
    exit();
}

$order = Order::loadOrder($order);

echo json_encode($order->items);
?>