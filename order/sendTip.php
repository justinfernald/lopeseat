<?php
require('../api.php');
require('../ledger/Ledger.php');

if (!isLoggedIn()) {
    result(false, "Not logged in");
}

$user = $GLOBALS['user'];

$amount = $_GET['amount'];

$db = new db();
$ledger = new Ledger();

$stmt = $db->prepare("SELECT id,total,transaction_id,transaction_amount,submitted,deliverer FROM Orders WHERE `user_id`=? AND `state`='completed' AND (UNIX_TIMESTAMP(CURRENT_TIMESTAMP())-UNIX_TIMESTAMP(`arrived`)) < (60*60) AND `id` NOT IN (SELECT `order_id` FROM PostTips)");
$stmt->bind_param("i", $user->id);
$db->exec();
$result = $db->get();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();

    if ($row['submitted'] == 1) {
        $nonce = $_POST['nonce'];
        $useBal = $_POST['useBal'];

        $usingBalance = isset($_POST['useBal']);

        if ($usingBalance) {
            $balance = $ledger->getQuickBalance($user->id, intval($useBal));
            
            if ($balance < $amount) {
                result(false, "Not enough money in balance!");
            }
            
            if ($useBal == "1") {
                $ledger->transferDeliveryTipFromLEB($user->id,$row['deliverer'],$amount);
            } else {
                $ledger->transferDeliveryTipFromDB($user->id,$row['deliverer'],$amount);
            }
        } else {
            if (!isset($nonce)) {
                result(false, "Payment method required");
            }

            $result = $gateway->transaction()->sale([
                'amount' => $amount,
                'paymentMethodNonce' => $nonce,
                'options' => [
                    'submitForSettlement' => true,
                ],
            ]);

            if (!$result->success) {
                result(false, $result->errors->deepAll());
            }
        }
    }
    
    $transactionId = isset($_POST['useBal']) ? ($_POST['useBal'] == "1" ? "BALANCE" : "EARNINGS") : $row['transaction_id'];
    $stmt = $db->prepare("INSERT INTO PostTips (order_id,amount,transaction_id,`time`) VALUES (?,?,?,CURRENT_TIMESTAMP)");
    $stmt->bind_param("iis", $row['id'], $amount, $transactionId);

    $db->exec();
    $ledger->transferDeliveryTip($user->id,$row['deliverer'],$amount);

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