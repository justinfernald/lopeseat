<?php
require('api.php');


$user = $GLOBALS['user'];

if ($user == null) {
    result(false, "Not logged into a delivery account");
    exit();
}

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    result(false, "Invalid ID");
    exit();
}

$orderId = (int)$_GET['id'];

$delivererId = $user->id;

$db = new db();
$stmt = $db->prepare("SELECT Users.name AS customerName, Orders.id AS orderId, Orders.address, Orders.state AS orderState, Orders.total AS totalPrice, UNIX_TIMESTAMP(Orders.placed) * 1000 AS timePlaced, UNIX_TIMESTAMP(Orders.claimed) * 1000 AS timeClaimed, UNIX_TIMESTAMP(Orders.en_route) * 1000 AS timeEnRoute, UNIX_TIMESTAMP(Orders.arrived) * 1000 AS timeArrived, Restaurants.name AS restaurantName, JSON_ARRAYAGG( JSON_OBJECT( \"name\", MenuItems.name, \"price\", MenuItems.price, \"items\", MenuItems.items, \"image\", MenuItems.image, \"amount\", OrderItems.amount, \"options\", OrderItems.options, \"comment\", OrderItems.comment) ) AS items FROM Orders JOIN OrderItems ON Orders.id = OrderItems.order_id JOIN MenuItems ON OrderItems.item_id = MenuItems.id INNER JOIN Restaurants ON MenuItems.restaurant_id = Restaurants.id JOIN Users ON Orders.user_id = Users.id WHERE Orders.id = ? AND Orders.deliverer = ?");
$stmt->bind_param("ii", $orderId, $delivererId);

$db->exec();
$results = $db->get();

if ($order = $results->fetch_object()) {
    $order->items = json_decode($order->items);
    result(true, $order);
    exit();
}

result(false, "Order doesn't exist");

?>