<?php
require('../api.php');

if (!isLoggedIn()) {
    result(false, "Not logged in");
}

$user = $GLOBALS['user'];

$amount = $_GET['amount'];

$db = new db();

$stmt = $db->prepare("SELECT id,total,transaction_id,transaction_amount FROM Orders WHERE `user_id`=? AND submitted=0 AND `state`='completed' AND (UNIX_TIMESTAMP(CURRENT_TIMESTAMP())-UNIX_TIMESTAMP(`arrived`)) < (60*60) AND `id` NOT IN (SELECT `order_id` FROM PostTips)");
$stmt->bind_param("i", $user->id);
$db->exec();
$result = $db->get();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    $stmt = $db->prepare("INSERT INTO PostTips (order_id,amount) VALUES (?,?)");
    $stmt->bind_param("ii", $row['id'], $amount);

    $db->exec();

    $success = FALSE;
    if ($secrets->braintree->environment == "sandbox") {
        $success = TRUE;
    } else {
        $btResult = $gateway->transaction()->submitForSettlement($row['transaction_id'], $row['transaction_amount'] + $amount);
        $success = $btResult->success;
    }

    if ($success) {
        $stmt = $db->prepare("UPDATE Orders SET submitted=1 WHERE id=?");
        $stmt->bind_param("i", $row['id']);
        $db->exec();
    }

    result($success);
} else {
    result(false, "No tippable order");
}

?>