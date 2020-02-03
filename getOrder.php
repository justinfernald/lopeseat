<?php
require_once('api.php');

$user = $GLOBALS['user'];

if ($user == null) {
    result(false, "Not logged in");
    exit();
}

$db = new db();

$stmt = $db->prepare("SELECT * FROM `Orders` WHERE `user_id`=?");
$stmt->bind_param("i",$user->id);

$db->exec();
$results = $db->get();

$order = $results->fetch_object();

echo json_encode($order);
?>