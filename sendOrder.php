<?php
require('api.php');

$deliveryfee = 3.75;

$user = $GLOBALS['user'];

if ($user == null) {
    result(false, "Not logged in");
    exit();
}

$cart = Cart::loadCart($user->id);

#$nonce = 'fake-venmo-account-nonce';
$nonce = $_POST['nonce'];
$address = $_POST['address'];
$total = $cart->getTotal();

$result = $gateway->transaction()->sale([
    'amount' => strval($deliveryfee),
    'paymentMethodNonce' => $nonce,
    'options' => [
      'submitForSettlement' => True
    ]
  ]);

if ($result->success) {
  $db = new db();
  $stmt = $db->prepare("INSERT INTO Orders (user_id, address, total, delivery_fee) VALUES (?,?,?,?)");
  $stmt->bind_param("isss",$user->id,$address,$total,$deliveryfee);
  $db->exec();

  for ($i = 0; $i < sizeof($cart->items); $i++) {
    $item = $cart->items[$i];
    $stmt = $db->prepare("INSERT INTO OrderItems (order_id, item_id, amount, comment) VALUES (?,?,?,?)");
    $order_id = $GLOBALS['conn']->insert_id;
    $stmt->bind_param("iiis", $order_id, $item->item_id, $item->amount, $item->comment);
    $db->exec();
  }

  $stmt = $db->prepare("DELETE FROM CartItems WHERE user_id=?");
  $stmt->bind_param("i", $user->id);
  $db->exec();
  result(true, $result->transaction->status);
} else
  result($result->success, $result->transaction->status);
?>