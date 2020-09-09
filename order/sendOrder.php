<?php
require '../api.php';
require '../ledger/Ledger.php';

$deliveryfee = 4;

$user = $GLOBALS['user'];

if (!isLoggedIn()) {
  result(false, "Not logged in");
}

$db = new db();

if ($user->deliverer) {
  $stmt = $db->prepare("SELECT `start` FROM DeliveryMode WHERE user_id=? ORDER BY `time` DESC");
  $stmt->bind_param("i",$user->id);
  $db->exec();
  $result = $db->get();

  if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if (strcmp($row['start'],"1") === 0) {
      result(false, "Currently in delivery mode.");
    }
  }
}

$restaurant = -1;

$cart = Cart::loadCart($user->id);
if (sizeof($cart->items)) {
    $restaurant = $cart->items[0]->restaurant_id;
}

if (!isRestaurantOpen($restaurant, (new DateTime("now", new DateTimeZone("America/Phoenix")))->add(new DateInterval("PT30M")))) {
  result(false, "This restaurant has finished accepting orders for today.");
}

$stmt = $db->prepare("SELECT count(id) as `count` FROM Orders WHERE `user_id`=? AND `state`<>'completed'");
$stmt->bind_param("i", $user->id);
$db->exec();
$result = $db->get();

$row = $result->fetch_assoc();

if ($row['count'] > 0) {
  result(false, "You already have an active order.");
}

#$nonce = 'fake-venmo-account-nonce';
$nonce = $_POST['nonce'];
$paymentType = $_POST['type'];
$cardType = $_POST['cardType'];
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
$submitted = 1;
if ($chargeAmount != 0) {
  $submit = true;
  if ($paymentType == "CreditCard" && ($cardType == "Visa" || $cardType == "MasterCard")) {
    $submit = false;
    $submitted = 0;
  }
  
  $result = $gateway->transaction()->sale([
      'amount' => strval($chargeAmount),
      'paymentMethodNonce' => $nonce,
      'options' => [
          'submitForSettlement' => $submit,
      ],
  ]);
  $success = $result->success;
}

if ($success) {
    $transId = $chargeAmount == 0 ? ($useBal == "1" ? "BALANCE" : "EARNINGS") : $result->transaction->id;

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