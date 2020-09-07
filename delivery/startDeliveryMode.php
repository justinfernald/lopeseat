<?php
require('../api.php');

$user = $GLOBALS['user'];

if ($user == null || !$user->deliverer) {
    result(false, "Not logged in.");
    exit();
}

$userId = $user->id;

$db = new db();

$stmt = $db->prepare("SELECT COUNT(id) as `count` FROM Orders WHERE `state`<>'completed' AND `user_id`=?");
$stmt->bind_param("i",$userId);
$db->exec();
$result = $db->get();

$row = $result->fetch_assoc();

if (strcmp($row['count'], "0") != 0) {
    result(false, "You have a pending order.");
}

$stmt = $db->prepare("SELECT start FROM DeliveryMode WHERE user_id=? ORDER BY time DESC LIMIT 1");
$stmt->bind_param("i",$userId);
$db->exec();

$row = $db->get()->fetch_object();
if ($row->start == 1) {
    result(false, "Already in delivery mode.");
    exit();
}

$stmt = $db->prepare("INSERT INTO `DeliveryMode` (`id`, `user_id`, `start`, `time`) VALUES (NULL, ?, 1, NULL)");
$stmt->bind_param("i",$userId);
$db->exec();

$stmt = $db->prepare("SELECT id,UNIX_TIMESTAMP(time)*1000 as time FROM DeliveryMode WHERE user_id=? AND start=1 ORDER BY time DESC LIMIT 1");
$stmt->bind_param("i",$userId);
$db->exec();

$row = $db->get()->fetch_object();

$message = array("sessionId"=>$row->id,"startingTime"=>$row->time);
result(true, $message);
?>