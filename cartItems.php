<?php
include('api.php');

$user = $GLOBALS['user'];

if ($user == null) {
    result(false, "Not logged in");
    exit();
}

$cart = Cart::loadCart($user->id);

for ($i = 0; $i < sizeof($cart->items); $i++) {
    $cart->items[$i]->price = $cart->items[$i]->getTotal();
}

echo json_encode($cart->items);
?>