<?php
require('../api.php');

$user = $GLOBALS['user'];

if ($user == null || !$user->deliverer) {
    result(false, "Not logged in.");
    exit();
}

$userId = $user->id;

$db = new db();

$stmt = $db->prepare("SELECT start FROM DeliveryMode WHERE user_id=? ORDER BY time DESC LIMIT 1");
$stmt->bind_param("i",$userId);
$db->exec();

$row = $db->get()->fetch_object();
if ($row->start == 0) {
    result(false, "Not in delivery mode.");
    exit();
}

$stmt = $db->prepare("INSERT INTO `DeliveryMode` (`id`, `user_id`, `start`, `time`) VALUES (NULL, ?, 0, NULL)");
$stmt->bind_param("i",$userId);
$db->exec();

$stmt = $db->prepare("SELECT id,UNIX_TIMESTAMP(time)*1000 as time FROM DeliveryMode WHERE user_id=? AND start=0 ORDER BY time DESC LIMIT 1");
$stmt->bind_param("i",$userId);
$db->exec();

$row = $db->get()->fetch_object();

$message = array("sessionId"=>$row->id,"stoppingTime"=>$row->time);
result(true, $message);
?>