<?php
require('api.php');

$user = $GLOBALS['user'];

if ($user == null) {
    result(false, "Not logged into a delivery account");
    exit();
}

$delivererId = $user->id;

$db = new db();

$stmt = $db->prepare("SELECT Users.name as customerName,Orders.id as orderId,Orders.address,Orders.state as orderState,UNIX_TIMESTAMP(Orders.placed) * 1000 as timePlaced,UNIX_TIMESTAMP(Orders.claimed) * 1000 as timeClaimed,UNIX_TIMESTAMP(Orders.en_route) * 1000 as timeEnRoute,UNIX_TIMESTAMP(Orders.arrived) * 1000 as timeArrived,Restaurants.name as restaurantName FROM Orders INNER JOIN Users ON Orders.user_id = Users.id INNER JOIN (SELECT OrderItems.order_id,OrderItems.item_id FROM OrderItems GROUP BY OrderItems.order_id) as OrderItem ON Orders.id = OrderItem.order_id INNER JOIN MenuItems ON OrderItem.item_id = MenuItems.id INNER JOIN Restaurants ON MenuItems.restaurant_id = Restaurants.id WHERE Orders.state NOT IN (\"completed\", \"cancelled\") AND Orders.deliverer=?");
$stmt->bind_param("i", $delivererId);

$db->exec();
$results = $db->get();

$orders = [];

while($order = $results->fetch_object()) {
    array_push($orders, $order);
}

echo json_encode($orders);
?>