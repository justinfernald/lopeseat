<?php
require('api.php');

use Twilio\Rest\Client;

$phone = $_POST["phone"];

$db = new db();

$stmt = $db->prepare("SELECT token FROM Users WHERE phone=?");
$stmt->bind_param("s", $phone);
$db->exec();

$result = $db->get();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $vCode = $row["token"];
    
    $sid = "ACabd0af5c10c6a5af597761521ba19abd";
    $token = "96e1075f638f3db1553dd7e34618c832";
    $twilio = new Client($sid, $token);
    
    $message = $twilio->messages->create($phone, array(
        "body" => "We are resending your LopesEat code! Your code is $vCode",
        "from" => "+17207456737"
    ));
    result(true);
} else {
    result(false,"Could not find phone number.");
}
?>