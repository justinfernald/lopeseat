<?php
require '../api.php';

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

$db = new db();

$stmt = $db->prepare("SELECT COUNT(Orders.id) as count FROM Orders WHERE Orders.state NOT IN (\"completed\", \"cancelled\") AND Orders.deliverer=?");
$stmt->bind_param("i", $delivererId);

$db->exec();
$results = $db->get();

$count = $results->fetch_object()->count;

$stmt = $db->prepare("SELECT COUNT(Orders.id) as count FROM (SELECT order_id, MAX(time_created) AS time_max FROM `DelivererRequest` WHERE deliverer_id=19 GROUP BY order_id) AS LatestDelivererRequest INNER JOIN DelivererRequest ON LatestDelivererRequest.order_id = DelivererRequest.order_id AND LatestDelivererRequest.time_max = DelivererRequest.time_created INNER JOIN Orders ON DelivererRequest.order_id = Orders.id WHERE DelivererRequest.status_id = 1");
$db->exec();
$results = $db->get();

$count += $results->fetch_object()->count;

echo result(true, $count);
