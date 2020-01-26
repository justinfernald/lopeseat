<?php
require('api.php');

use Kreait\Firebase;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

$states = array("unclaimed", "claimed", "en route", "arrived");

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

$stmt = $db->prepare("UPDATE Orders SET state=? WHERE id=?");
$stmt->bind_param("si",$state,$order);
$db->exec();

$deliverer_user = getUser($user_id);
$token = $deliverer_user->FBToken;
$name = $deliverer_name = $deliverer_user->name;

$notification;

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

$title = str_replace("%deliverer%", $deliverer_name, $notification->title);
$body = str_replace("%deliverer%", $deliverer_name, $notification->body);

if ($token != null) {
    $messaging = (new Firebase\Factory())->withServiceAccount($serviceAccountPath)->createMessaging();

    $message = CloudMessage::withTarget('token',  $token)
        ->withNotification(Notification::create($title, $body));
    $result = $messaging->send($message);
}

result(true);
?>