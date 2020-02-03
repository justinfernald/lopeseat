<?php
require('api.php');

use Kreait\Firebase;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

$states = array("unclaimed", "claimed", "en route", "arrived", "completed");

$db = new db();

$order = $_GET['id'];
$state = $_GET['state'];

$deliverer = $GLOBALS['user'];

if ($deliverer->deliverer == 0) {
    result(false, "Not logged into a delivery account");
    exit();
}

if (!in_array(strtolower($state),$states)) {
    result(false, "Invalid state: $state");
    exit();
}

$stmt = $db->prepare("SELECT user_id FROM Orders WHERE deliverer=? AND id=?");
$stmt->bind_param("ii", $deliverer->id, $order);
$db->exec();
$results = $db->get();

if ($results->num_rows == 0) {
    result(false, "This order is not assigned to the given delivery account.");
    exit();
}

$user_id = $results->fetch_assoc()['user_id'];
$index = array_search(strtolower($state), $states);

if ($index > 0 && $index < 4) {
    $timeColumn = str_replace(" ", "_", strtolower($state));
    $time = gmdate("Y-m-d H:i:s");
    $stmt = $db->prepare("UPDATE Orders SET state=?, $timeColumn=? WHERE id=?");
    $stmt->bind_param("ssi",$state,$time,$order);
} else {
    $stmt = $db->prepare("UPDATE Orders SET state=? WHERE id=?");
    $stmt->bind_param("si",$state,$order);
}
$db->exec();

$deliverer_user = getUser($user_id);
$token = $deliverer_user->FBToken;
$name = $deliverer_name = $deliverer_user->name;

$notification = null;

switch (strtolower($state)) {
case 'unclaimed':
    $notification = $messages->notifications->order_unclaimed;
    break;
case 'claimed':
    $notification = $messages->notifications->order_claimed;
    break;
case 'en route':
    $notification = $messages->notifications->order_en_route;
    break;
case 'arrived':
    $notification = $messages->notifications->order_arrived;
    break;
}

if ($notification != null) {
    $title = str_replace("%deliverer%", $deliverer_name, $notification->title);
    $body = str_replace("%deliverer%", $deliverer_name, $notification->body);
    $data = [
        "title" => $title,
        "body" => $body,
        "state" => $state
    ];

    if ($token != null) {
        $messaging = (new Firebase\Factory())->withServiceAccount($serviceAccountPath)->createMessaging();

        $message = CloudMessage::withTarget('token',  $token)->withData($data);
        $result = $messaging->send($message);
    }
}

result(true);
?>