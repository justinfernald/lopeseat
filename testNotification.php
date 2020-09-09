<?php
require('api.php');

use Kreait\Firebase;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

$title = $_GET['title'];
$body = $_GET['body'];
$user = $_GET['user'];

$db = new db();

$stmt = $db->prepare("SELECT FBToken FROM Users WHERE id=?");
$stmt->bind_param("i", $user);
$db->exec();
$result = $db->get();

$token = $result->fetch_assoc()['FBToken'];

$serviceAccountPath = sprintf("%s/config/service_account.json", __DIR__);

$messaging = (new Firebase\Factory())->withServiceAccount($serviceAccountPath)->createMessaging();

$data = [
    "title" => $title,
    "body" => $body,
    "state" => "test",
];

$message = CloudMessage::withTarget('token', $token)->withData($data);

try {
    $result = $messaging->send($message);
    var_dump($result);
} catch (NotFound $e) {
    var_dump($e);
}
?>