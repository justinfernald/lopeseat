<?php
require('../api.php');

$rid = $_GET['rid'];

$db = new db();
$stmt = $db->prepare("SELECT * FROM MenuItems WHERE restaurant_id=?");
$stmt->bind_param("i",$rid);
$db->exec();
$results = $db->get();

$items = [];

while($item = $results->fetch_object()) {
    array_push($items, $item);
}

echo json_encode($items);
?>