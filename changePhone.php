<?php
require('api.php');

use Twilio\Rest\Client;

if (!isLoggedIn()) {
    result(false, "Not logged in");
    exit();
}

$db = new db();

$phone = $_POST["phone"];
$token = randomNum();

if (strlen($phone) < 10) {
    echo strlen($phone);
    result(false, "Invalid phone number");
    exit();
}

$stmt = $db->prepare("SELECT phone FROM `Users` WHERE phone=? UNION SELECT phone FROM `PhoneConfirmations` WHERE phone=?");
$stmt->bind_param("ss",$phone,$phone);

$db->exec();

$result = $db->get();

if ($result->num_rows > 0) {
    result(false, "Phone number is already in use.");
    exit();
}

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