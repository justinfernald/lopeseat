<?php
require('../api.php');
require('../ledger/Ledger.php');

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

$stmt = $db->prepare("SELECT user_id,state,delivery_fee,tip FROM Orders WHERE id = ? AND (deliverer=? OR (deliverer=-1 AND state='unclaimed'))");
$stmt->bind_param("ii", $order, $deliverer->id);
$db->exec();
$results = $db->get();

if ($results->num_rows == 0) {
    result(false, "Order has been claimed by another deliverer.");
    exit();
}

$row = $results->fetch_assoc();
$user_id = $row['user_id'];
$fee = intval($row['delivery_fee']);
$tip = intval($row['tip']);
$index = array_search(strtolower($state), $states);

if ($index != 1 && $row['state']=="unclaimed") {
    result(false, "Order must move from unclaimed to claimed");
    exit();
}

if ($row['state'] == "completed") {
    result(false, "Cannot update a completed order");
}

if ($index > 0 && $index < 4) {
    $timeColumn = str_replace(" ", "_", strtolower($state));
    $time = gmdate("Y-m-d H:i:s");
    $stmt = $db->prepare("UPDATE Orders SET state=?, $timeColumn=CURRENT_TIMESTAMP, deliverer=? WHERE id=?");
    $stmt->bind_param("sii",$state,$deliverer->id,$order);
} else {
    if ($index = 0)
        $deliverer->id = -1;
    $stmt = $db->prepare("UPDATE Orders SET state=?,deliverer=? WHERE id=?");
    $stmt->bind_param("sii",$state,$deliverer->id,$order);
}
$db->exec();

$user = getUser($user_id);
$token = $user->FBToken;
$deliverer_name = $deliverer->name;

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
case 'completed':
    $ledger = new Ledger();
    $ledger->transferDeliveryEarnings($deliverer->id, $fee);
    if ($tip != 0)
        $ledger->transferDeliveryTip($user_id, $deliverer->id, $tip);
    break;
}

if ($notification != null) {
    $title = str_replace("%deliverer%", $deliverer_name, $notification->title);
    $body = str_replace("%deliverer%", $deliverer_name, $notification->body);
    $data = [
        "title" => $title,
        "body" => $body,
        // "state" => $state
        "state" => "order_update"
    ];

    if ($token != null) {
        $messaging = (new Firebase\Factory())->withServiceAccount($GLOBALS['serviceAccountPath'])->createMessaging();

        $message = CloudMessage::withTarget('token',  $token)->withData($data);
        $result = $messaging->send($message);
    }
}

result(true);
?>