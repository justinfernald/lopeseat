<?php
require('api.php');

$user = $GLOBALS['user'];
$orderId = $_POST['orderId'];
$timeShown = $_POST['timeShown'];

if (!isset($orderId) || !isset($timeShown)) {
    result(false, "Not the correct stuff inputted");
    exit();
}

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

$stmt = $db->prepare("SELECT COUNT(id) as count FROM `Orders` WHERE id=? AND deliverer=?");
$stmt->bind_param("ii", $orderId, $delivererId);
$db->exec();
$results = $db->get();
if ($results->fetch_assoc()['count'] == 0) {
    result(false, "Not an order");
    exit();
}

$stmt = $db->prepare("INSERT INTO `BarcodeUsage` (`id`, `order_id`, `time_shown`, `time_occurred`) VALUES (NULL, ?, ?, CURRENT_TIMESTAMP)");
$stmt->bind_param("ii", $orderId, $timeShown);

$db->exec();

echo result(true);
?>