<?php
require('../api.php');

$user = $GLOBALS['user'];

if (!isLoggedIn() || $user->deliverer === 0) {
    result(false, "Not logged into a delivery account.");
    exit();
}

$paypalToken = getPaypalToken();
$payoutTotal = $user->getPayoutTotal(true);

echo $paypalToken;

if ($payoutTotal == 0) {
    result(false, "No available payouts");
    exit();
}

$url = "https://api.sandbox.paypal.com/v1/payments/payouts";

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
if (curl_errno($ch)) {
    print "Error: " . curl_error($ch);
    exit();
}
curl_close($ch);

$jsonResult = json_decode($result);
$batchId = $jsonResult->batch_header->payout_batch_id;

$user->updateOrderPayouts($batchId);

echo $result;
?>