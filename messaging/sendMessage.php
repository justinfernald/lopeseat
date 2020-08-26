<?php
require('../api.php');

use Kreait\Firebase;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

$orderId = $_GET['orderId'];
$messageString = $_POST['message'];
$user = $GLOBALS['user'];

$db = new db();

$stmt = $db->prepare("SELECT * FROM Orders WHERE state!='completed' AND id=? AND (user_id=? OR deliverer=?)");
$stmt->bind_param("iii", $orderId, $user->id, $user->id);

$db->exec();
$results = $db->get();

if ($results->num_rows == 0) {
    result(false, "Order not found");
    exit(0);
}

$order = $results->fetch_assoc();
$recipient = $order['user_id'];

if ($recipient == $user->id)
    $recipient = $order['deliverer'];

$stmt = $db->prepare("INSERT INTO Messages (order_id, sender, message, time) VALUES (?,?,?,?)");
$time = gmdate("Y-m-d H:i:s");
$stmt->bind_param("iiss", $orderId, $user->id, $messageString, $time);
$db->exec();

$stmt = $db->prepare("SELECT FBToken FROM Users WHERE id=?");
$stmt->bind_param("i", $recipient);
$db->exec();
$results = $db->get();

if ($results->num_rows > 0) {
    $token = $results->fetch_assoc()['FBToken'];

    $title = $messages->notifications->message_received->title;
    $body = $messages->notifications->message_received->body;
    $title = str_replace("%sender%", $user->name, $title);
    $title = str_replace("%message%", $messageString, $title);
    $body = str_replace("%sender%", $user->name, $body);
    $body = str_replace("%message%", $messageString, $body);

    $data = [
        "title" => $title,
        "body" => $body,
        "message" => $messageString,
        "sender" => $user->name
    ];

    try {
        $messaging = (new Firebase\Factory())->withServiceAccount($serviceAccountPath)->createMessaging();

        $message = CloudMessage::withTarget('token', $token)->withData($data);
        $result = $messaging->send($message);
    } catch (Exception $e) {
    }
}
result(true, "Message sent");
exit(0);
?>