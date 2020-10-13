<?php
require_once('../api.php');

$user = $GLOBALS['user'];
    
$taxPercentage = 0.086;
$fee = 1.99;

if ($user == null) {
    result(false, "Not logged in");
    exit();
}

$canOrder = true;
$message = null;

$restaurant = -1;

$cart = Cart::loadCart($user->id);

$subTotal = $cart->getTotal();
$tax = $subTotal * $taxPercentage;
$total = $subTotal + $tax + ($cart->track_inv ? $fee : 0);

if (sizeof($cart->items)) {
    $restaurant = $cart->items[0]->restaurant_id;

    if (!$cart->isAvailable()) {
        $canOrder = false;
        $message = $cart->unavailable[0]->name." is out of stock.";
    }
}

if ($restaurant !== -1 && !isRestaurantOpen($restaurant, (new DateTime("now", new DateTimeZone("America/Phoenix")))->add(new DateInterval("PT30M")))) {
    $canOrder = false;
    $message = "This restaurant has finished accepting orders for today.";
}

$db = new db();

$stmt = $db->prepare("SELECT count(id) as `count` FROM Orders WHERE `user_id`=? AND `state`<>'completed'");
$stmt->bind_param("i", $user->id);
$db->exec();
$result = $db->get();

$row = $result->fetch_assoc();

if ($row['count'] > 0) {
    $canOrder = false;
    $message = "You already have an active order.";
}
echo json_encode(array(
    "subtotal" => $subTotal, 
    "tax" => $tax, 
    "total" => $total, 
    "delivery_fee" => $fee, 
    "can_order" => $canOrder,
    "need_payment" => $cart->track_inv,
    "msg" => $message
));
?>