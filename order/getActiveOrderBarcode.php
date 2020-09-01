<?php
require '../api.php';
$generator = new Picqer\Barcode\BarcodeGeneratorHTML();

$user = $GLOBALS['user'];

if ($user == null) {
    result(false, "Not logged into a delivery account");
    exit();
}

if ($user->deliverer == 0) {
    result(false, "Not on a delivery account");
    exit();
}

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    result(false, "Invalid ID");
    exit();
}

$orderId = (int) $_GET['id'];

$delivererId = $user->id;

$db = new db();
$stmt = $db->prepare("SELECT Users.student_id AS studentId FROM Orders JOIN OrderItems ON Orders.id = OrderItems.order_id JOIN MenuItems ON OrderItems.item_id = MenuItems.id INNER JOIN Restaurants ON MenuItems.restaurant_id = Restaurants.id JOIN Users ON Orders.user_id = Users.id WHERE Orders.state NOT IN (\"completed\", \"cancelled\") AND Orders.id = ? AND Orders.deliverer = ?");
$stmt->bind_param("ii", $orderId, $delivererId);

$db->exec();
$results = $db->get();

if ($order = $results->fetch_object()) {
    if ($order->studentId == null) {
        result(false, "Order doesn't exist");
        exit();
    }

    echo $generator->getBarcode($order->studentId, $generator::TYPE_CODE_39);
    exit();
}

result(false, "Order doesn't exist");
