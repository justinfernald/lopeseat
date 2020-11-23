<?php
require_once "vendor/autoload.php";
use Twilio\TwiML\MessagingResponse;

header("content-type: text/xml");

$response = new MessagingResponse();
$response->message(
    "Thank you for using LopesEat. We don't currently received these texts. If you'd like to message us, use the messaging in the app."
);

echo $response;
?>