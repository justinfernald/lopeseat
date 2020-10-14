<?php
require('../api.php');

use Twilio\Rest\Client;

$phone = $_POST['phone'];
$vCode = randomNum();

$db = new db();

$stmt = $db->prepare("INSERT INTO `PhoneConfirmations` (`user_id`, `phone`, `token`) VALUES ((SELECT `id` FROM `Users` WHERE `phone`=?),?,?)");
$stmt->bind_param("sss", $phone, $phone, $vCode);
$db->exec();

$twilio = new Client($secrets->twilio->sid, $secrets->twilio->token);

$message = $twilio->messages->create($phone, array(
    "body" => "Your LopesEat recovery code is $vCode",
    "from" => "+17207456737"
));

result(true);
?>