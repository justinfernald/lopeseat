<?php
require_once('api.php');

$order = $_GET['orderId'];

$deliverer = $GLOBALS['user'];

if ($deliverer->deliverer == 0) {
    result(false, "Not on a delivery account");
    exit();
}

$db = new db();
$stmt = $db->prepare("SELECT user_id FROM Orders WHERE deliverer=? AND id=?");
$stmt->bind_param("ii", $deliverer->id, $order);

$db->exec();
$results = $db->get();

if ($results->num_rows == 0) {
    result(false, "No order found");
    exit();
}

$row = $results->fetch_assoc();
$user = getUser($row['user_id']);

$data = array(
    student_id => $user->student_id,
    deliverer_image => $deliverer->profile_image
);

echo json_encode($data);
?>