<?php
require_once '../api.php';

use Kreait\Firebase;
use Kreait\Firebase\Messaging\CloudMessage;
use Twilio\Rest\Client;

function getQueue()
{
    $db = new db();
    $stmt = $db->prepare("SELECT eligible_deliverers.*, DelivererRequestTime.time_requested AS time_requested, GREATEST( COALESCE(time_claimed, 0), COALESCE(time_arrived, 0), COALESCE(time_started, 0), COALESCE( DelivererRequestTime.time_requested, 0) ) AS time_max FROM( SELECT active_deliverers.user_id AS deliverer_id, COALESCE( SUM(Orders.state IN('claimed')), 0 ) AS active_orders, MAX(Orders.claimed) AS time_claimed, MAX(Orders.arrived) AS time_arrived, active_deliverers.time AS time_started FROM ( SELECT DeliveryMode.* FROM ( SELECT user_id, MAX(TIME) AS TIME FROM DeliveryMode GROUP BY user_id ) AS latest_change INNER JOIN DeliveryMode ON DeliveryMode.user_id = latest_change.user_id AND DeliveryMode.time = latest_change.time WHERE START = 1 ) AS active_deliverers LEFT JOIN Orders ON active_deliverers.user_id = Orders.deliverer WHERE Orders.deliverer <> active_deliverers.user_id OR NOT EXISTS( SELECT Orders.deliverer AS deliverer FROM Orders WHERE active_deliverers.user_id = Orders.deliverer AND( Orders.state IN('en route', 'arrived') ) ) GROUP BY Orders.deliverer ) AS eligible_deliverers LEFT OUTER JOIN( SELECT DelivererRequest.* FROM ( SELECT DelivererRequest.order_id, MAX(DelivererRequest.time_created) AS latest_time FROM DelivererRequest GROUP BY DelivererRequest.order_id ) AS LatestDelivererRequest INNER JOIN DelivererRequest ON LatestDelivererRequest.order_id = DelivererRequest.order_id AND LatestDelivererRequest.latest_time = DelivererRequest.time_created WHERE DelivererRequest.status_id = 1 ) AS NonPendingRequest ON eligible_deliverers.deliverer_id = NonPendingRequest.deliverer_id LEFT JOIN( SELECT deliverer_id, MAX(`time_created`) AS time_requested FROM DelivererRequest GROUP BY deliverer_id ) AS DelivererRequestTime ON eligible_deliverers.deliverer_id = DelivererRequestTime.deliverer_id WHERE NonPendingRequest.id IS NULL AND active_orders <= 2 ORDER BY active_orders ASC, time_max ASC");
    $db->exec();

    $result = $db->get();

    $queue = [];
    while ($row = $result->fetch_object()) {
        $payload = array(
            "id" => $row->id,
            "title" => $row->title,
            "description" => $row->description,
            "tag" => $row->tag,
            "url" => $row->url,
            "image" => $row->image,
            "card_location" => $row->card_location,
        );
        array_push($queue, $payload);
    }

    return $queue;
}

function getQueuedDeliverer($orderId)
{
    $db = new db();

    $stmt = $db->prepare("SELECT eligible_deliverers.*, DelivererRequestTime.time_requested AS time_requested, GREATEST( COALESCE(time_claimed, 0), COALESCE(time_arrived, 0), COALESCE(time_started, 0), COALESCE( DelivererRequestTime.time_requested, 0) ) AS time_max FROM( SELECT active_deliverers.user_id AS deliverer_id, COALESCE( SUM(Orders.state IN('claimed')), 0 ) AS active_orders, MAX(Orders.claimed) AS time_claimed, MAX(Orders.arrived) AS time_arrived, active_deliverers.time AS time_started FROM ( SELECT DeliveryMode.* FROM ( SELECT user_id, MAX(TIME) AS TIME FROM DeliveryMode GROUP BY user_id ) AS latest_change INNER JOIN DeliveryMode ON DeliveryMode.user_id = latest_change.user_id AND DeliveryMode.time = latest_change.time WHERE START = 1 ) AS active_deliverers LEFT JOIN Orders ON active_deliverers.user_id = Orders.deliverer WHERE Orders.deliverer <> active_deliverers.user_id OR NOT EXISTS( SELECT Orders.deliverer AS deliverer FROM Orders WHERE active_deliverers.user_id = Orders.deliverer AND( Orders.state IN('en route', 'arrived') ) ) GROUP BY Orders.deliverer ) AS eligible_deliverers LEFT OUTER JOIN( SELECT DelivererRequest.* FROM ( SELECT DelivererRequest.order_id, MAX(DelivererRequest.time_created) AS latest_time FROM DelivererRequest GROUP BY DelivererRequest.order_id ) AS LatestDelivererRequest INNER JOIN DelivererRequest ON LatestDelivererRequest.order_id = DelivererRequest.order_id AND LatestDelivererRequest.latest_time = DelivererRequest.time_created WHERE DelivererRequest.status_id = 1 ) AS NonPendingRequest ON eligible_deliverers.deliverer_id = NonPendingRequest.deliverer_id LEFT JOIN( SELECT deliverer_id, MAX(`time_created`) AS time_requested FROM DelivererRequest GROUP BY deliverer_id ) AS DelivererRequestTime ON eligible_deliverers.deliverer_id = DelivererRequestTime.deliverer_id WHERE NonPendingRequest.id IS NULL AND active_orders <= 2 AND NOT EXISTS( SELECT id FROM DelivererRequest WHERE DelivererRequest.order_id = ? AND DelivererRequest.deliverer_id = eligible_deliverers.deliverer_id ) ORDER BY active_orders ASC, time_max ASC LIMIT 1");
    $stmt->bind_param("i", $orderId);
    $db->exec();
    $result = $db->get();
    if ($row = $result->fetch_object()) {
        return $row->deliverer_id;
    }

    return null;
}

function requestDeliverer($orderId)
{
    $delivererId = getQueuedDeliverer($orderId);

    if ($delivererId == null) {
        return false;
    }

    $db = new db();

    $stmt = $db->prepare("INSERT INTO `DelivererRequest` (`id`, `order_id`, `deliverer_id`, `status_id`) VALUES (NULL, ?, ?, '1')");
    $stmt->bind_param("ii", $orderId, $delivererId);
    $db->exec();

    $stmt = $db->prepare("SELECT name, FBToken, phone FROM Users WHERE id=?");
    $stmt->bind_param("i", $delivererId);
    $db->exec();
    $rowDeliverer = $db->get()->fetch_object();

    // need to notify deliverer
    $notification = $GLOBALS['messages']->notifications->deliverer_request;

    $title = str_replace("%deliverer%", $rowDeliverer->name, $notification->title);
    $body = str_replace("%deliverer%", $rowDeliverer->name, $notification->body);
    $data = [
        "title" => $title,
        "body" => $body,
        "state" => "deliverer_request/$orderId",
    ];

    if ($rowDeliverer->FBToken != null) {
        $messaging = (new Firebase\Factory())->withServiceAccount($GLOBALS['serviceAccountPath'])->createMessaging();

        $message = CloudMessage::withTarget('token', $rowDeliverer->FBToken)->withData($data);
        $result = $messaging->send($message);

        $twilio = new Client($GLOBAL['secrets']->twilio->sid, $GLOBAL['secrets']->twilio->token);
        $messagePhone = $twilio->messages->create($rowDeliverer->phone, array(
            "body" => "$title : $body - Open the LopesEat app to message your runner",
            "from" => "+17207456737",
        ));

    }

    // need to notify next up
}

function getExpiredOrders()
{
    $timeAllowed = 90; // in seconds
    $db = new db();
    // there is no need to convert to milliseconds since UNIX_TIMESTAMP in sql returns in seconds
    $stmt = $db->prepare("SELECT DelivererRequest.order_id, DelivererRequest.deliverer_id FROM (SELECT order_id, MAX(time_created) AS latest_time FROM DelivererRequest GROUP BY DelivererRequest.order_id) AS LatestDelivererRequest INNER JOIN DelivererRequest ON LatestDelivererRequest.order_id = DelivererRequest.order_id AND LatestDelivererRequest.latest_time = DelivererRequest.time_created WHERE DelivererRequest.status_id='1' AND UNIX_TIMESTAMP(DelivererRequest.time_created) < (UNIX_TIMESTAMP(CURRENT_TIMESTAMP) - ?) ORDER BY DelivererRequest.time_created");
    $stmt->bind_param("i", $timeAllowed);
    $db->exec();
    $result = $db->get();
    $orderIds = array();
    while ($row = $result->fetch_object()) {
        $stmt = $db->prepare("INSERT INTO `DelivererRequest` (`id`, `order_id`, `deliverer_id`, `status_id`) VALUES (NULL, ?, ?, '4')");
        $stmt->bind_param("ii", $row->order_id, $row->deliverer_id);
        $db->exec();

        $stmt = $db->prepare("SELECT name, FBToken, phone FROM Users WHERE id=?");
        $stmt->bind_param("i", $row->deliverer_id);
        $db->exec();
        $rowDeliverer = $db->get()->fetch_object();

        // need to notify deliverer
        $notification = $GLOBALS['messages']->notifications->deliverer_past_time;

        $title = str_replace("%deliverer%", $rowDeliverer->name, $notification->title);
        $body = str_replace("%deliverer%", $rowDeliverer->name, $notification->body);
        $data = [
            "title" => $title,
            "body" => $body,
            "state" => "expired_request",
        ];

        if ($rowDeliverer->FBToken != null) {
            $messaging = (new Firebase\Factory())->withServiceAccount($GLOBALS['serviceAccountPath'])->createMessaging();

            $message = CloudMessage::withTarget('token', $rowDeliverer->FBToken)->withData($data);
            $notificationResult = $messaging->send($message);

            $twilio = new Client($GLOBAL['secrets']->twilio->sid, $GLOBAL['secrets']->twilio->token);
            $messagePhone = $twilio->messages->create($rowDeliverer->phone, array(
                "body" => "$title : $body",
                "from" => "+17207456737",
            ));

        }
        array_push($orderIds, $row->order_id);
    }

    return $orderIds;
}

function getUnrequestedOrders()
{
    $db = new db();

    $stmt = $db->prepare("SELECT Orders.id FROM Orders LEFT OUTER JOIN( SELECT DelivererRequest.* FROM ( SELECT order_id, MAX(time_created) AS time_created FROM DelivererRequest GROUP BY order_id ) AS LatestDelivererRequest INNER JOIN DelivererRequest ON DelivererRequest.order_id = LatestDelivererRequest.order_id AND DelivererRequest.time_created = LatestDelivererRequest.time_created ) AS DelivererRequestInfo ON DelivererRequestInfo.order_id = Orders.id WHERE (DelivererRequestInfo.id IS NULL OR DelivererRequestInfo.status_id <> 2 AND DelivererRequestInfo.status_id <> 1) AND Orders.state = 'unclaimed' ORDER BY Orders.placed");

    $db->exec();
    $result = $db->get();
    $orderIds = array();
    while ($row = $result->fetch_object()) {
        array_push($orderIds, $row->id);
    }

    return $orderIds;
}

function getAcceptableOrders($delivererId)
{
    $db = new db();

    $stmt = $db->prepare("SELECT Orders.id, UNIX_TIMESTAMP(DelivererRequest.time_created) * 1000 AS timeRequested, Restaurants.name AS restaurantName FROM (SELECT order_id, MAX(time_created) AS time_max FROM `DelivererRequest` WHERE deliverer_id=? GROUP BY order_id) AS LatestDelivererRequest INNER JOIN DelivererRequest ON LatestDelivererRequest.order_id = DelivererRequest.order_id AND LatestDelivererRequest.time_max = DelivererRequest.time_created INNER JOIN Orders ON DelivererRequest.order_id = Orders.id INNER JOIN OrderItems ON Orders.id = OrderItems.order_id INNER JOIN MenuItems ON OrderItems.item_id = MenuItems.id INNER JOIN Restaurants ON MenuItems.restaurant_id = Restaurants.id WHERE DelivererRequest.status_id = 1");
    $stmt->bind_param("i", $delivererId);
    $db->exec();
    $result = $db->get();
    $orders = array();
    while ($row = $result->fetch_object()) {
        array_push($orders, $row);
    }

    return $orders;
}

function isOrderClaimed($orderId)
{
    $db = new db();

    $stmt = $db->prepare("SELECT id FROM Orders WHERE id = ? AND state='unclaimed'");
    $stmt->bind_param("i", $orderId);
    $db->exec();
    $results = $db->get();

    return $results->num_rows == 0;
}

function isDeliverersOrder($delivererId, $orderId)
{
    $db = new db();

    $stmt = $db->prepare("SELECT id FROM Orders WHERE id = ? AND deliverer=?");
    $stmt->bind_param("ii", $orderId, $delivererId);
    $db->exec();
    $results = $db->get();

    return $results->num_rows != 0;
}

function isOrderAcceptable($delivererId, $orderId)
{
    $db = new db();

    $stmt = $db->prepare("SELECT DelivererRequest.id, UNIX_TIMESTAMP(DelivererRequest.time_created) * 1000 AS timeCreated FROM (SELECT order_id, MAX(time_created) AS time_max FROM `DelivererRequest` WHERE order_id=? AND deliverer_id=? GROUP BY order_id) AS LatestDelivererRequest INNER JOIN DelivererRequest ON LatestDelivererRequest.order_id = DelivererRequest.order_id AND LatestDelivererRequest.time_max = DelivererRequest.time_created WHERE DelivererRequest.status_id = 1");
    $stmt->bind_param("ii", $orderId, $delivererId);
    $db->exec();
    $results = $db->get();

    if ($row = $results->fetch_object()) {
        return $row->timeCreated;
    }

    return false;
}

function acceptDelivererRequest($delivererId, $orderId)
{
    $db = new db();

    if (isOrderClaimed($orderId) || !isOrderAcceptable($delivererId, $orderId)) {
        return false;
    }

    $stmt = $db->prepare("INSERT INTO `DelivererRequest` (`id`, `order_id`, `deliverer_id`, `status_id`) VALUES (NULL, ?, ?, '2')");
    $stmt->bind_param("ii", $orderId, $delivererId);
    $db->exec();

    $stmt = $db->prepare("UPDATE Orders SET state='claimed',deliverer=?,claimed=CURRENT_TIMESTAMP WHERE id=?");
    $stmt->bind_param("ii", $delivererId, $orderId);
    $db->exec();

    return true;
}

function declineDelivererRequest($delivererId, $orderId)
{
    $db = new db();

    if (!isOrderAcceptable($delivererId, $orderId)) {
        return false;
    }

    $stmt = $db->prepare("INSERT INTO `DelivererRequest` (`id`, `order_id`, `deliverer_id`, `status_id`) VALUES (NULL, ?, ?, '3')");
    $stmt->bind_param("ii", $orderId, $delivererId);
    $db->exec();

    return true;
}

function pastTimeDelivererRequest($delivererId, $orderId)
{
    $db = new db();

    $stmt = $db->prepare("INSERT INTO `DelivererRequest` (`id`, `order_id`, `deliverer_id`, `status_id`) VALUES (NULL, ?, ?, '4')");
    $stmt->bind_param("ii", $orderId, $delivererId);
    $db->exec();

    return true;
}
