<?php
require '../api.php';

$orderId = $_GET['orderId'];
$messageString = $_POST['message'];
$user = $GLOBALS['user'];

if (!isLoggedIn()) {
    result(false, "Not logged in");
}

sendMessage($messageString, $orderId, $user->id);

result(true, "Message sent");
exit(0);
