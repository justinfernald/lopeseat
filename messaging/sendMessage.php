<?php
require('../api.php');

use Kreait\Firebase;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

$orderId = $_GET['orderId'];
$messageString = $_POST['message'];
$user = $GLOBALS['user'];

if (!isLoggedIn()) {
    result(false, "Not logged in");
}

sendMessage($messageString, $orderId, $user->id);
result(true, "Message sent");
exit(0);
?>