<?php
require_once('../api.php');

$user = $GLOBALS['user'];
    
$taxPercentage = 0.086;
$fee = 3.99;

if ($user == null) {
    result(false, "Not logged in");
    exit();
}

$canOrder = true;

$restaurant = -1;

$cart = Cart::loadCart($user->id);
if (sizeof($cart->items)) {
    $restaurant = $cart->items[0]->restaurant_id;
}

if ($restaurant !== -1 && !isRestaurantOpen($restaurant, (new DateTime("now", new DateTimeZone("America/Phoenix")))->add(new DateInterval("PT30M")))) {
    $canOrder = false;
}

$subTotal = $cart->getTotal();
$tax = $subTotal * $taxPercentage;
$total = $subTotal + $tax;
echo json_encode(array("subtotal" => $subTotal, "tax" => $tax, "total" => $total, "delivery_fee" => $fee, "can_order" => $canOrder));
?>