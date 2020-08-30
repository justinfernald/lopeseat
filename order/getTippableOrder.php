<?php
require('../api.php');

if (!isLoggedIn()) {
    result(false, "Not logged in");
}

$user = $GLOBALS['user'];

$db = new db();

$stmt = $db->prepare("SELECT id,deliverer,total,delivery_fee FROM Orders WHERE submitted=0 AND `user_id`=? AND `state`='completed' AND (UNIX_TIMESTAMP(CURRENT_TIMESTAMP())-UNIX_TIMESTAMP(`arrived`)) < (60*60) AND `id` NOT IN (SELECT `order_id` FROM PostTips)");
$stmt->bind_param("i", $user->id);
$db->exec();
$result = $db->get();

if ($result->num_rows > 0) {
    $obj = $result->fetch_object();
    echo json_encode($obj);
} else {
    echo "null";
}
?>