<?php
require('../api.php');

$user = $GLOBALS['user'];
$itemId = $_GET['id'];
$amount = $_GET['amount'];
$options = $_GET['options'];
$comment = $_GET['comment'];

if ($user == null) {
    result(false, "Not logged in");
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
}

if (!ctype_digit($amount) || intval($amount) <= 0) {
    result(false, "Invalid amount");
}

if ($restaurant != -1 && $restaurant != $result->fetch_assoc()['restaurant_id']) {
    result(false, "Item is from a different restaurant.");
}

if ($cart->count() >= $GLOBALS['cartMax']) {
    result(false, "You can only order up to " . $GLOBALS['cartMax'] . " items.");
}

$stmt = $db->prepare("SELECT a.amount_available, a.item_id FROM InventoryChanges a 
INNER JOIN (SELECT item_id, MAX(id) as m_id FROM InventoryChanges GROUP BY item_id) as b on b.m_id = a.id AND a.item_id=?");
$stmt->bind_param("i", $itemId);
$db->exec();
$result = $db->get();

// $amountInCart = $cart->countItem($itemId);
$amountInCart = 0;

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if ($row['amount_available'] == 0) {
        result(false, "That item is out of stock.");
    } else if ($row['amount_available'] < $amount + $amountInCart) {
        result(false, "We only have ".$row['amount_available']." of that item in stock currently.");
    }
} else {
    result(false, "That item is out of stock.");
}

$stmt = $db->prepare("INSERT INTO `CartItems` (`user_id`, `item_id`, `amount`, `comment`, `options`) VALUES (?,?,?,?,?)");
$stmt->bind_param("sssss", $user->id, $itemId, $amount, $comment, $options);
if ($db->exec()) {
    result(true, array("id" => $GLOBALS['conn']->insert_id, "debug" => json_encode($cart->items)));
}
?>
