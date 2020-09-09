<?php

require '../api.php';

$user = $GLOBALS['user'];

if ($user == null || !$user->deliverer) {
    result(false, "Not logged in.");
    exit();
}

$userId = $user->id;

$db = new db();

$stmt = $db->prepare("SELECT start FROM DeliveryMode WHERE user_id=? LIMIT 1");
$stmt->bind_param("i", $userId);
$db->exec();

if ($row = $db->get()->fetch_object()) {
    result(true, true);
} else {
    result(true, false);
}
