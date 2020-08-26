<?php
require('../api.php');
require('./Ledger.php');

$amount = $_POST['amount'];
$nonce = $_POST['nonce'];

if (!isLoggedIn()) {
    result(false, "Not logged in");
}

if (!isset($_POST['amount'])) {
    result(false, "No amount provided");
}

if (!isset($_POST['nonce'])) {
    result(false, "No payment provided");
}

$user = $GLOBALS['user'];

$receiver = $user->id;

if (isset($_POST['to'])) {
    $db = new db();

    $stmt = $db->prepare("SELECT id FROM Users WHERE phone=?");
    $stmt->bind_param("i",$_POST['to']);

    $db->exec();
    $result = $db->get();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $receiver = $row['id'];
    }
}

$result = $gateway->transaction()->sale([
    'amount' => $amount,
    'paymentMethodNonce' => $nonce,
    'options' => [
      'submitForSettlement' => True
    ]
  ]);

if ($result->success) {
    $ledger = new Ledger();
    
    $ledger->transferCashToLEB($user->id, $receiver, $amount);
    result(true, $result->transaction->status);
} else {
    result($result->success, $result->transaction->status);
}

?>