<?php
require_once('../api.php');

function getQueue() {
    $db = new db();
    $stmt = $db->prepare("SELECT eligible_deliverers.*, DelivererRequestTime.time_requested AS time_requested, GREATEST( COALESCE(time_claimed, 0), COALESCE(time_arrived, 0), COALESCE(time_started, 0), COALESCE( DelivererRequestTime.time_requested, 0) ) AS time_max FROM( SELECT active_deliverers.user_id AS deliverer_id, COALESCE( SUM(Orders.state IN('claimed')), 0 ) AS active_orders, MAX(Orders.claimed) AS time_claimed, MAX(Orders.arrived) AS time_arrived, active_deliverers.time AS time_started FROM ( SELECT DeliveryMode.* FROM ( SELECT user_id, MAX(TIME) AS TIME FROM DeliveryMode GROUP BY user_id ) AS latest_change INNER JOIN DeliveryMode ON DeliveryMode.user_id = latest_change.user_id AND DeliveryMode.time = latest_change.time WHERE START = 1 ) AS active_deliverers LEFT JOIN Orders ON active_deliverers.user_id = Orders.deliverer WHERE Orders.deliverer <> active_deliverers.user_id OR NOT EXISTS( SELECT Orders.deliverer AS deliverer FROM Orders WHERE active_deliverers.user_id = Orders.deliverer AND( Orders.state IN('en route', 'arrived') ) ) GROUP BY Orders.deliverer ) AS eligible_deliverers LEFT OUTER JOIN( SELECT DelivererRequest.* FROM ( SELECT DelivererRequest.order_id, MAX(DelivererRequest.time_created) AS latest_time FROM DelivererRequest GROUP BY DelivererRequest.order_id ) AS LatestDelivererRequest INNER JOIN DelivererRequest ON LatestDelivererRequest.order_id = DelivererRequest.order_id AND LatestDelivererRequest.latest_time = DelivererRequest.time_created WHERE DelivererRequest.status_id = 1 ) AS NonPendingRequest ON eligible_deliverers.deliverer_id = NonPendingRequest.deliverer_id LEFT JOIN( SELECT deliverer_id, MAX(`time_created`) AS time_requested FROM DelivererRequest GROUP BY deliverer_id ) AS DelivererRequestTime ON eligible_deliverers.deliverer_id = DelivererRequestTime.deliverer_id WHERE NonPendingRequest.id IS NULL AND active_orders <= 2 ORDER BY active_orders ASC, time_max ASC");
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

function getQueuedDeliverer() {
    $db = new db();

    $stmt = $db->prepare("SELECT eligible_deliverers.*, DelivererRequestTime.time_requested AS time_requested, GREATEST( COALESCE(time_claimed, 0), COALESCE(time_arrived, 0), COALESCE(time_started, 0), COALESCE( DelivererRequestTime.time_requested, 0) ) AS time_max FROM( SELECT active_deliverers.user_id AS deliverer_id, COALESCE( SUM(Orders.state IN('claimed')), 0 ) AS active_orders, MAX(Orders.claimed) AS time_claimed, MAX(Orders.arrived) AS time_arrived, active_deliverers.time AS time_started FROM ( SELECT DeliveryMode.* FROM ( SELECT user_id, MAX(TIME) AS TIME FROM DeliveryMode GROUP BY user_id ) AS latest_change INNER JOIN DeliveryMode ON DeliveryMode.user_id = latest_change.user_id AND DeliveryMode.time = latest_change.time WHERE START = 1 ) AS active_deliverers LEFT JOIN Orders ON active_deliverers.user_id = Orders.deliverer WHERE Orders.deliverer <> active_deliverers.user_id OR NOT EXISTS( SELECT Orders.deliverer AS deliverer FROM Orders WHERE active_deliverers.user_id = Orders.deliverer AND( Orders.state IN('en route', 'arrived') ) ) GROUP BY Orders.deliverer ) AS eligible_deliverers LEFT OUTER JOIN( SELECT DelivererRequest.* FROM ( SELECT DelivererRequest.order_id, MAX(DelivererRequest.time_created) AS latest_time FROM DelivererRequest GROUP BY DelivererRequest.order_id ) AS LatestDelivererRequest INNER JOIN DelivererRequest ON LatestDelivererRequest.order_id = DelivererRequest.order_id AND LatestDelivererRequest.latest_time = DelivererRequest.time_created WHERE DelivererRequest.status_id = 1 ) AS NonPendingRequest ON eligible_deliverers.deliverer_id = NonPendingRequest.deliverer_id LEFT JOIN( SELECT deliverer_id, MAX(`time_created`) AS time_requested FROM DelivererRequest GROUP BY deliverer_id ) AS DelivererRequestTime ON eligible_deliverers.deliverer_id = DelivererRequestTime.deliverer_id WHERE NonPendingRequest.id IS NULL AND active_orders <= 2 ORDER BY active_orders ASC, time_max ASC LIMIT 1");

    $db->exec();
    $result = $db->get();
    if ($row = $result->fetch_object()) {
        return $row->deliverer_id;
    }

    return null;
}

function requestDeliverer($orderId) {
    $delivererId = getQueuedDeliverer();

    if ($delivererId == null) {
        return false;
    }

    $db = new db();

    $stmt = $db->prepare("INSERT INTO `DelivererRequest` (`id`, `order_id`, `deliverer_id`, `status_id`) VALUES (NULL, ?, ?, '1')");
    $stmt->bind_param("ii", $orderId, $delivererId);
    $db->exec();

    // need to notify deliverer
    // need to notify next up
}

function getExpiredOrders() {
    $timeAllowed = 30; // in seconds

    // there is no need to convert to milliseconds since UNIX_TIMESTAMP in sql returns in seconds
    $stmt = $db->prepare("SELECT DelivererRequest.order_id FROM (SELECT order_id, MAX(time_created) AS latest_time FROM DelivererRequest GROUP BY DelivererRequest.order_id) AS LatestDelivererRequest INNER JOIN DelivererRequest ON LatestDelivererRequest.order_id = DelivererRequest.order_id AND LatestDelivererRequest.latest_time = DelivererRequest.time_created WHERE DelivererRequest.status_id='1' AND UNIX_TIMESTAMP(DelivererRequest.time_created) < (UNIX_TIMESTAMP(CURRENT_TIMESTAMP) - ?) ORDER BY DelivererRequest.time_created");
    $stmt->bind_param("i", $timeAllowed);
    $db->exec();
    $result = $db->get();
    $orderIds = array();
    while ($row = $result->fetch_object()) {
        array_push($row, $row->order_id);
    }

    return $orderIds;
}

function getUnrequestedOrders() {
    $db = new db();

    $stmt = $db->prepare("SELECT Orders.id FROM `Orders` LEFT OUTER JOIN DelivererRequest ON Orders.id = DelivererRequest.order_id WHERE DelivererRequest.id IS NULL AND state='unclaimed' ORDER BY Orders.placed");

    $db->exec();
    $result = $db->get();
    $orderIds = array();
    while ($row = $result->fetch_object()) {
        array_push($row, $row->id);
    }

    return $orderIds;
}

function acceptDelivererRequest($delivererId) {

}

function declineDelivererRequest($delivererId) {

}

function pastTimeDelivererRequest($delivererId) {

}

?>