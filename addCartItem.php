<?php
require('api.php');

$user = $GLOBALS['user'];
$itemId = $_GET['id'];
$amount = $_GET['amount'];
$comment = $_GET['comment'];

if ($user == null) {
    result(false, "Not logged in");
    exit();
}

$restaurant = -1;

$cart = Cart::loadCart($user->id);
if (sizeof($cart->items)) {
    $restaurant = $cart->items[0]->restaurant_id;
}

$db = new db();

$stmt = $db->prepare("SELECT * FROM MenuItems WHERE id=?");
$stmt->bind_param("i",$itemId);
$db->exec();
$result = $db->get();

if ($result->num_rows == 0) {
    result(false, "Unknown item id");
    exit();
}

if (!ctype_digit($amount) || intval($amount) <= 0) {
    result(false, "Invalid amount");
    exit();
}

if ($restaurant != -1 && $restaurant != $result->fetch_assoc()['restaurant_id']) {
    result(false, "Item is from a different restaurant.");
    exit();
}

$stmt = $db->prepare("INSERT INTO `CartItems` (`user_id`, `item_id`, `amount`, `comment`) VALUES (?,?,?,?)");
$stmt->bind_param("ssss", $user->id, $itemId, $amount, $comment);
if ($db->exec()) {
    result(true);
}
?>