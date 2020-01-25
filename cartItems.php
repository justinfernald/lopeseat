<?php
    include('api.php');

    $user = $GLOBALS['user'];
    
    if ($user == null) {
        result(false, "Not logged in");
        exit();
    }

    $cart = Cart::loadCart($user->id);

    echo json_encode($cart->items);
?>