<?php
require('../api.php');
require("../ledger/Ledger.php");

$user = $GLOBALS['user'];

if (!isLoggedIn() || $user->deliverer === 0) {
    result(false, "Not logged into a delivery account.");
    exit();
}

$ledger = new Ledger();

$paypalToken = getPaypalToken();
$payoutTotal = $ledger->getQuickBalance($user->id, 2);

if ($payoutTotal == 0) {
    result(false, "No available payouts");
    exit();
}

$url = "https://api.paypal.com/v1/payments/payouts";

$content = json_encode(array(
    "sender_batch_header"=>array(
        "sender_batch_id"=>"Payout_".$user->id."_".gmdate("YmdHi"),
        "recipient_type"=>"EMAIL",
        "email_subject"=>"LopesEat Payout",
        "email_message"=>"Thank you for delivering with LopesEat! Follow the details below to receive your payout."
    ),
    "items"=>array(
        array(
            "recipient_type"=>"EMAIL",
            "amount"=>array(
                "value"=>number_format($payoutTotal,2),
                "currency"=>"USD"
            ),
            "sender_item_id"=>gmdate("YmdHi"),
            "receiver"=>$user->email,
        )
    )
));

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Content-Type: application/json",
    "Authorization: Bearer " . $paypalToken
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$result = curl_exec($ch);
$jsonResult = json_decode($result);
if (curl_errno($ch)) {
    result(false, "Error: " . curl_error($ch));
} else {
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code !== 200) {
        result(false, $jsonResult);
    }
}
curl_close($ch);

$batchId = $jsonResult->batch_header->payout_batch_id;

$ledger->transferCashFromDB($user->id, $payoutTotal, $batchId);

result(true, $jsonResult);
?>