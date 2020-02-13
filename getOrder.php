<?php
require_once('api.php');

$user = $GLOBALS['user'];

if ($user == null) {
    result(false, "Not logged in");
    exit();
}

$db = new db();

$stmt = $db->prepare("SELECT * FROM `Orders` WHERE `user_id`=? AND `state`!='completed'");
$stmt->bind_param("i",$user->id);

$db->exec();
$results = $db->get();

if ($results->num_rows == 0) {
    echo "null";
    exit();
}

$order = $results->fetch_object();

$restaurant = -1;

$orderObj = Order::loadOrder($order->id);
if (sizeof($orderObj->items)) {
    $restaurant = $orderObj->items[0]->restaurant_id;
}

$order->restaurant_id = $restaurant;

$stmt = $db->prepare("SELECT wait FROM `Restaurants` WHERE `id`=?");
$stmt->bind_param("i", $restaurant);

$db->exec();
$results = $db->get();

$waitTime = $results->fetch_assoc()['wait'];
$waitTime -= (mktime(gmdate("H, i, s, m, d, Y")) - strtotime($order->placed)) / 60;
$order->wait = $waitTime;

echo json_encode($order);
?>