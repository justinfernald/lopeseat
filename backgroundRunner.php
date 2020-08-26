<?php
require_once('api.php');
require('deliveryQueue.php');
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
    $orderIds = getUnclaimedOrders();
    foreach ($orderIds as $orderId) {
        
    }
}

backgroundRunner();
?>