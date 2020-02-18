<?php
require('api.php');

$user = $GLOBALS['user'];
$itemId = $_GET['id'];
$amount = $_GET['amount'];
$options = $_GET['options'];
$comment = $_GET['comment'];

if ($user == null) {
    result(false, "Not logged in");
    exit();
}

$db = new db();

$stmt = $db->prepare("SELECT * FROM MenuItems WHERE id=?");
$stmt->bind_param("i",$itemId);
$db->exec();
$result = $db->get();

if ($result->num_rows == 0) {
    result(false, "Unknown item id");
    exit();
}

if (!ctype_digit($amount) || intval($amount) <= 0) {
    result(false, "Invalid amount");
    exit();
}

$stmt = $db->prepare("INSERT INTO `CartItems` (`user_id`, `item_id`, `amount`, `comment`, `options`) VALUES (?,?,?,?,?)");
$stmt->bind_param("sssss", $user->id, $itemId, $amount, $comment, $options);
if ($db->exec()) {
    result(true);
}
?>
