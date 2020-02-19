<?php
require_once('api.php');

$orderId = $_GET['orderId'];
$user = $GLOBALS['user'];

$db = new db();

$stmt = $db->prepare("SELECT * FROM Orders WHERE id=? AND (user_id=? OR deliverer=?)");
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
$recipientUser = getUser($recipient);

$stmt = $db->prepare("SELECT * FROM Messages WHERE order_id=? ORDER BY time ASC");
$stmt->bind_param("i", $orderId);
$db->exec();
$results = $db->get();
$messages = array();

while ($message = $results->fetch_object()) {
    array_push($messages, $message);
}

$data = [
    "selfId" => $user->id,
    "selfName" => $user->name,
    "otherId" => $recipient,
    "otherName" => $recipientUser->name,
    "messages" => $messages
];

echo json_encode($data);
?>