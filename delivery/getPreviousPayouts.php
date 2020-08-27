<?php
require('../api.php');

if (!isLoggedIn()) {
    result(false, "Not logged in");
}

$user = $GLOBALS['user'];

$db = new db();
$stmt = $db->prepare("SELECT * FROM Payouts WHERE user_id=? ORDER BY time DESC");
$stmt->bind_param("i", $user->id);

$db->exec();
$result = $db->get();

$payouts = Array();

while($row = $result->fetch_assoc()) {
    $status = $row['status'];
    if ($status !== "SUCCESS") {
        $status = getPayoutStatus($row['batch_id']);
        // $stmt = $db->prepare("UPDATE Payouts SET `status`=? WHERE id=?");
        // $stmt->bind_param("si",$status,$row['id']);
        // $db->exec();
    }
    $payoutObj = Array(
        'id' => $row['id'],
        'status' => $status,
        'amount' => $row['amount'],
        'time' => $row['time']
    );
    array_push($payouts, $payoutObj);
}

echo json_encode($payouts);
?>