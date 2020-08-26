<?php
require('../api.php');

$user = $GLOBALS['user'];

if ($user == null || $user->deliverer == 0) {
    result(false, "Not logged into a delivery account.");
    exit();
}

$orderId = $_GET['order'];

if (!isset($orderId)) {
    result(false, "No order id given.");
    exit();
}

$paypalToken = getPaypalToken();

$db = new db();
$stmt = $db->prepare("SELECT payoutBatchId FROM Orders WHERE id=?");
$stmt->bind_param("i", $orderId);

$db->exec();
$results = $db->get();

if ($results->num_rows == 0) {
    result(false, "No order found");
    exit();
}

$batchId = $results->fetch_assoc()['payoutBatchId'];

$url = "https://api.sandbox.paypal.com/v1/payments/payouts/$batchId";

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);
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

$result_json = json_decode($result);

echo json_encode(array("status"=>$result_json->batch_header->batch_status));
?>