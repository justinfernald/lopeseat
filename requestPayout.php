<?php
require('api.php');

//Get paypal token
$auth = base64_encode($paypalClientId . ":" . $paypalSecret);

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, "https://api.sandbox.paypal.com/v1/oauth2/token");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Content-Type: application/x-www-form-urlencoded",
    "Authorization: Basic " . $auth
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$result = curl_exec($ch);
if (curl_errno($ch)) {
    print "Error: " . curl_error($ch);
    exit();
}
curl_close($ch);
$result = json_decode($result);
$paypalToken = $result->access_token;

$user = $GLOBALS['user'];

if (!isLoggedIn() || $user->deliverer === 0) {
    result(false, "Not logged into a delivery account.");
    exit();
}

$db = new db();
$stmt = $db->prepare("SELECT * FROM Orders WHERE deliverer=? AND state='completed' AND payoutReceived=0");
$stmt->bind_param("i", $user->id);
$db->exec();
$results = $db->get();

if ($results->num_rows == 0) {
    result(false, "No available payouts");
    exit();
}

$payoutTotal = 0;
$payoutOrderIds = array();

while ($row = $results->fetch_assoc()) {
    $time = strtotime($row['arrived']);
    $now = strtotime(gmdate("Y-m-d H:i:s"));
    if (($now - $time) / 3600 >= 1) {
        $payoutTotal += $row['delivery_fee'] / 2;
        array_push($payoutOrderIds, "id=" . $row['id']);
    }
}

if ($payoutTotal == 0) {
    result(false, "No available payouts");
    exit();
}

$condition = implode(" OR ", $payoutOrderIds);

$stmt = $db->prepare("UPDATE Orders SET payoutReceived=1 WHERE $condition");
$db->exec();

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
echo $result;
?>