<?php
require('../api.php');

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

echo result(true, $count);
?>