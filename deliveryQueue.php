<?php
require_once('api.php');

function getQueue() {
    $db = new db();
    $stmt = $db->prepare("SELECT eligible_deliverers.*, GREATEST( COALESCE(time_claimed, 0), COALESCE(time_arrived, 0), COALESCE(time_started, 0)) AS time_max FROM( SELECT active_deliverers.user_id AS deliverer_id, COALESCE(SUM(Orders.state IN('claimed')), 0) AS active_orders, MAX(Orders.claimed) AS time_claimed, MAX(Orders.arrived) AS time_arrived, active_deliverers.time AS time_started FROM ( SELECT DeliveryMode.* FROM ( SELECT user_id, MAX(TIME) AS TIME FROM DeliveryMode GROUP BY user_id ) AS latest_change INNER JOIN DeliveryMode ON DeliveryMode.user_id = latest_change.user_id AND DeliveryMode.time = latest_change.time WHERE START = 1 ) AS active_deliverers LEFT JOIN Orders ON active_deliverers.user_id = Orders.deliverer WHERE Orders.deliverer <> active_deliverers.user_id OR NOT EXISTS ( SELECT Orders.deliverer as deliverer FROM Orders WHERE active_deliverers.user_id = Orders.deliverer AND( Orders.state IN('en route', 'arrived') ) ) GROUP BY Orders.deliverer ) AS eligible_deliverers WHERE active_orders <= 2 ORDER BY active_orders ASC, time_max ASC");
    $db->exec();

    $result = $db->get();

    $queue = [];
    while ($row = $result->fetch_object()) {
        $payload = array(
            "id"=>$row->id,
            "title"=>$row->title,
            "description"=>$row->description,
            "tag"=>$row->tag,
            "url"=>$row->url,
            "image"=>$row->image,
            "card_location"=>$row->card_location,
        );
        array_push($queue, $payload);
    }

    return $queue;
}

function getNextQueuedDeliverer() {
    $db = new db();
    
    $stmt = $db->prepare("SELECT eligible_deliverers.*, GREATEST( COALESCE(time_claimed, 0), COALESCE(time_arrived, 0), COALESCE(time_started, 0)) AS time_max FROM( SELECT active_deliverers.user_id AS deliverer_id, COALESCE(SUM(Orders.state IN('claimed')), 0) AS active_orders, MAX(Orders.claimed) AS time_claimed, MAX(Orders.arrived) AS time_arrived, active_deliverers.time AS time_started FROM ( SELECT DeliveryMode.* FROM ( SELECT user_id, MAX(TIME) AS TIME FROM DeliveryMode GROUP BY user_id ) AS latest_change INNER JOIN DeliveryMode ON DeliveryMode.user_id = latest_change.user_id AND DeliveryMode.time = latest_change.time WHERE START = 1 ) AS active_deliverers LEFT JOIN Orders ON active_deliverers.user_id = Orders.deliverer WHERE Orders.deliverer <> active_deliverers.user_id OR NOT EXISTS ( SELECT Orders.deliverer as deliverer FROM Orders WHERE active_deliverers.user_id = Orders.deliverer AND( Orders.state IN('en route', 'arrived') ) ) GROUP BY Orders.deliverer ) AS eligible_deliverers WHERE active_orders <= 2 ORDER BY active_orders ASC, time_max ASC LIMIT 1 OFFSET 1");

    $db->exec();
    $result = $db->get();
    if ($row = $result->fetch_object()) {
        return $row->deliverer_id;
    }

    return null;
}

function getQueuedDeliverer() {
    $db = new db();

    $stmt = $db->prepare("SELECT eligible_deliverers.*, GREATEST( COALESCE(time_claimed, 0), COALESCE(time_arrived, 0), COALESCE(time_started, 0)) AS time_max FROM( SELECT active_deliverers.user_id AS deliverer_id, COALESCE(SUM(Orders.state IN('claimed')), 0) AS active_orders, MAX(Orders.claimed) AS time_claimed, MAX(Orders.arrived) AS time_arrived, active_deliverers.time AS time_started FROM ( SELECT DeliveryMode.* FROM ( SELECT user_id, MAX(TIME) AS TIME FROM DeliveryMode GROUP BY user_id ) AS latest_change INNER JOIN DeliveryMode ON DeliveryMode.user_id = latest_change.user_id AND DeliveryMode.time = latest_change.time WHERE START = 1 ) AS active_deliverers LEFT JOIN Orders ON active_deliverers.user_id = Orders.deliverer WHERE Orders.deliverer <> active_deliverers.user_id OR NOT EXISTS ( SELECT Orders.deliverer as deliverer FROM Orders WHERE active_deliverers.user_id = Orders.deliverer AND( Orders.state IN('en route', 'arrived') ) ) GROUP BY Orders.deliverer ) AS eligible_deliverers WHERE active_orders <= 2 ORDER BY active_orders ASC, time_max ASC LIMIT 1");

    $db->exec();
    $result = $db->get();
    if ($row = $result->fetch_object()) {
        return $row->deliverer_id;
    }

    return null;
}

function requestDeliverer($orderId) {
    $delivererId = getQueuedDeliverer();
    $db = new db();

    $stmt = $db->prepare("INSERT INTO `DelivererRequest` (`id`, `order_id`, `deliverer_id`, `status_id`) VALUES (NULL, ?, ?, '1')");
    $stmt->bind_param("ii", $orderId, $delivererId);
    $db->exec();

    return null;
}

function getExpiredOrders() {
    $timeAllowed = 30; // in seconds

    // there is no need to convert to milliseconds since UNIX_TIMESTAMP in sql returns in seconds
    $stmt = $db->prepare("SELECT id FROM `DelivererRequest` WHERE status_id='1' AND UNIX_TIMESTAMP(time_created) > (UNIX_TIMESTAMP(CURRENT_TIMESTAMP) - ?) ORDER BY time_created");
    $stmt->bind_param("i", $timeAllowed);
    $db->exec();
    $result = $db->get();
    $orderIds = array();
    while ($row = $result->fetch_object()) {
        array_push($row, $row->id);
    }

    return $orderIds;
}

function getUnclaimedOrders() {
    $db = new db();

    $stmt = $db->prepare("SELECT id FROM `Orders` WHERE state='unclaimed' ORDER BY placed");

    $db->exec();
    $result = $db->get();
    $orderIds = array();
    while ($row = $result->fetch_object()) {
        array_push($row, $row->id);
    }

    return $orderIds;
}

?>