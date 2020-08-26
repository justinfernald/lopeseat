<?php
require_once('../api.php');

$user = $GLOBALS['user'];
    
$taxPercentage = 0.086;
$fee = 3.99;

if ($user == null) {
    result(false, "Not logged in");
    exit();
}

$cart = Cart::loadCart($user->id);

$subTotal = $cart->getTotal();
$tax = $subTotal * $taxPercentage;
$total = $subTotal + $tax;
echo json_encode(array("subtotal" => $subTotal, "tax" => $tax, "total" => $total, "delivery_fee" => $fee));
?>