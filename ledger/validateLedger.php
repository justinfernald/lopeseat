<?php
require('../api.php');
require('./Ledger.php');

use Twilio\Rest\Client;

$GLOBALS['key'] = $secrets->encryption->key;
$GLOBALS['iv'] = $secrets->encryption->iv;
$ledgerJson = openssl_encrypt('[]', 'aes-256-cbc', $GLOBALS['key'], 0, $GLOBALS['iv']);

$ledger = new Ledger();
$valid = $ledger->validateDatabase();
$report = $ledger->report;

echo json_encode(Array("valid" => $valid, "report" => $report));

if (!$valid) {
    $to = "cronupdate@lopeseat.com";
    $subject = "!URGENT! DISCREPENCY FOUND IN LEDGER";
    $msg = "report: \n" . json_encode($ledger->report, JSON_PRETTY_PRINT);
    $headers = 'From: <dev@lopeseat.com>' . "\r\n";

    mail($to, $subject, $msg, $headers);

    $twilio = new Client($secrets->twilio->sid, $secrets->twilio->token);

    $body = "!URGENT! DISCREPENCY FOUND IN LEDGER! report sent to dev@lopeseat.com and mailing list";
    $from = "+17207456737";

    for ($i = 0; $i < sizeof($secrets->emergency_phones); $i++) {
        $twilio->messages->create($secrets->emergency_phones[$i], array(
            "body" => $body,
            "from" => $from
        ));
    }
}
?>