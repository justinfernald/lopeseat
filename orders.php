<?php
require('api.php');

$state = $_GET['state'];
$user = $_GET['user'];

$queryCondition = "";

$db = new db();

if (isset($state) || isset($user)) {
    if (isset($state) && isset($user)) {
        $stmt = $db->prepare("SELECT * FROM `Orders` WHERE `state`=? AND `user_id`=?");
        $stmt->bind_param("si",$state, $user);
        // echo "state and user";
    } else if (isset($state)) {
        $stmt = $db->prepare("SELECT * FROM `Orders` WHERE `state`=?");
        $stmt->bind_param("s",$state);
        // echo "state:".$state;
    } else {
        $stmt = $db->prepare("SELECT * FROM `Orders` WHERE `user_id`=?");
        $stmt->bind_param("i",$user);
        // echo "user";
    }
} else {
    $db->prepare("SELECT * FROM `Orders`");
}

$db->exec();
$results = $db->get();

$orders = [];

while($order = $results->fetch_object()) {
    $orderItems = Order::loadOrder($order->id)->items;
    $order->restaurant_id = $orderItems[0]->restaurant_id;
    $order->restaurant_name = $orderItems[0]->restaurant_name;
    array_push($orders, $order);
}

echo json_encode($orders);
?>