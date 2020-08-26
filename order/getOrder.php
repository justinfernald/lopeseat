<?php
require('../api.php');

$user = $GLOBALS['user'];

if ($user == null) {
    result(false, "Not logged in");
    exit();
}

$db = new db();
$stmt;

if ($user->deliverer == 1 && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $db->prepare("SELECT * FROM `Orders` WHERE `id`=?");
    $stmt->bind_param("i", $id);
} else {
    $stmt = $db->prepare("SELECT * FROM `Orders` WHERE `user_id`=? AND `state`!='completed'");
    $stmt->bind_param("i",$user->id);
}

$db->exec();
$results = $db->get();

if ($results->num_rows == 0) {
    echo "null";
    exit();
}

$order = $results->fetch_object();

$restaurant = -1;
$restaurant_name = "";

$orderObj = Order::loadOrder($order->id);
if (sizeof($orderObj->items)) {
    $restaurant = $orderObj->items[0]->restaurant_id;
    $restaurant_name = $orderObj->items[0]->restaurant_name;
}

$order->restaurant_id = $restaurant;
$order->restaurant_name = $restaurant_name;

$stmt = $db->prepare("SELECT wait FROM `Restaurants` WHERE `id`=?");
$stmt->bind_param("i", $restaurant);

$db->exec();
$results = $db->get();

$waitTime = $results->fetch_assoc()['wait'];
$waitTime -= (mktime(gmdate("H, i, s, m, d, Y")) - strtotime($order->placed)) / 60;
$order->wait = $waitTime;

echo json_encode($order);
?>