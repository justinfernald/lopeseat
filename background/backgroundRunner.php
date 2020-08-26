<?php
require_once('../api.php');
require('../delivery/deliveryQueue.php');
$payload = $_GET['payload'];

$fp = fopen('backgroundRunner.log', 'a');
$milliseconds = round(microtime(true) * 1000);
fwrite($fp, "$milliseconds | $payload\n");  
fclose($fp);

echo "true";

function backgroundRunner() {
    handleQueue();
}

function handleQueue() {
    $expiredOrderIds = getExpiredOrders();
    $unrequestedOrderIds = getUnrequestedOrders();
    $orderIds = array_merge($expiredOrderIds, $unrequestedOrderIds);
    foreach ($orderIds as $orderId) {
        requestDeliverer($orderId);
    }
}

backgroundRunner();
?>