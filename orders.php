<?php
require('api.php');

$state = $_GET['state'];
$deliverer = $_GET['deliverer'];

$user = $GLOBALS['user'];

if ($user == null) {
    result(false, "Not logged into a delivery account");
    exit();
}

$delivererId = $user->id;

$queryCondition = "";

$db = new db();

if (isset($state) || isset($deliverer)) {
    if (isset($state) && isset($deliverer)) {
        $stateOperator = "=";
        if (substr($state, 0, 1) === "!") {
            $stateOperator = "<>";
            $state = substr($state, 1);
        }
        $stmt = $db->prepare("SELECT * FROM `Orders` WHERE `state`$stateOperator? AND `deliverer`=?");
        $stmt->bind_param("si",$state, $delivererId);
        // echo "state and user";
    } else if (isset($state)) {
        $stateOperator = "=";
        if (substr($state, 0, 1) === "!") {
            $stateOperator = "<>";
            $state = substr($state, 1);
        }
        $stmt = $db->prepare("SELECT * FROM `Orders` WHERE `state`$stateOperator?");
        $stmt->bind_param("s",$state);
        // echo "state:".$state;
    } else {
        $stmt = $db->prepare("SELECT * FROM `Orders` WHERE `deliverer`=?");
        $stmt->bind_param("i",$delivererId);
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