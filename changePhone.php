<?php
require('api.php');

use Twilio\Rest\Client;

if (!isLoggedIn()) {
    result(false, "Not logged in");
}

$db = new db();

$phone = $_POST["phone"];
$token = randomNum();

$stmt = $db->prepare("INSERT INTO `PhoneConfirmations` (`user_id`, `phone`, `token`) VALUES (?,?,?)");
$stmt->bind_param("iss", $GLOBALS['user']->id, $phone, $token);

$db->exec();

$twilio = new Client($secrets->twilio->sid, $secrets->twilio->token);

$message = $twilio->messages->create($phone, array(
    "body" => "Your LopesEat code is $token",
    "from" => "+17207456737"
));

result(true);
?>