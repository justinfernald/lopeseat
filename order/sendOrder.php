<?php
require '../api.php';
require '../ledger/Ledger.php';

$deliveryfee = 4;

$user = $GLOBALS['user'];

if ($user == null) {
    result(false, "Not logged in");
    exit();
}

$cart = Cart::loadCart($user->id);

#$nonce = 'fake-venmo-account-nonce';
$nonce = $_POST['nonce'];
$useBal = $_POST['useBal'];
$address = $_POST['address'];
$total = $cart->getTotal();

$usingBalance = isset($_POST['useBal']);

$chargeAmount = $deliveryfee;

if ($usingBalance) {
  $ledger = new Ledger();
  $balance = $ledger->getQuickBalance($user->id, intval($useBal));
  
  $chargeAmount = ($balance > $deliveryfee) ? 0 : $deliveryfee - $balance;

  if ($useBal == "1") {
    $ledger->transferDeliveryFeeFromLEB($user->id, $deliveryfee - $chargeAmount);
  } else {
    $ledger->transferDeliveryFeeFromDB($user->id, $deliveryfee - $chargeAmount);
  }
}

$success = true;
$result = null;
if ($chargeAmount != 0) {
  $result = $gateway->transaction()->sale([
      'amount' => strval($chargeAmount),
      'paymentMethodNonce' => $nonce,
      'options' => [
          'submitForSettlement' => false,
      ],
  ]);
  $success = $result->success;
}

if ($success) {
    $transId = $chargeAmount == 0 ? ($useBal == "1" ? "BALANCE" : "EARNINGS") : $result->transaction->id;
    $submitted = $chargeAmount == 0 ? 1 : 0;

    $db = new db();
    $stmt = $db->prepare("INSERT INTO Orders (user_id, address, total, delivery_fee, transaction_id, transaction_amount, submitted, placed) VALUES (?,?,?,?,?,?,?,CURRENT_TIMESTAMP)");
    $stmt->bind_param("isddsdi", $user->id, $address, $total, $deliveryfee, $transId, $chargeAmount, $submitted);
    $db->exec();
    $order_id = $GLOBALS['conn']->insert_id;

    for ($i = 0; $i < sizeof($cart->items); $i++) {
        $item = $cart->items[$i];
        $stmt = $db->prepare("INSERT INTO OrderItems (order_id, item_id, amount, comment, options) VALUES (?,?,?,?,?)");
        $stmt->bind_param("iiiss", $order_id, $item->item_id, $item->amount, $item->comment, $item->options);
        $db->exec();
    }

    $stmt = $db->prepare("DELETE FROM CartItems WHERE user_id=?");
    $stmt->bind_param("i", $user->id);
    $db->exec();
    result(true, $result == null ? "Used balance" : $result->transaction->status);
} else {
    result($result->success, $result->transaction->status);
}
?>