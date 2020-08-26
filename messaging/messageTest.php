<?php
require('../api.php');

use Twilio\Rest\Client;

$sid = "ACabd0af5c10c6a5af597761521ba19abd";
$token = "96e1075f638f3db1553dd7e34618c832";
$twilio = new Client($sid, $token);

$message = $twilio->messages->create("(505)239-7396", array(
    "body" => "Piss",
    "from" => "+17207456737"
));
echo $message->sid;
?>