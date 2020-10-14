<?php
require('../api.php');

$phone = $_POST['phone'];
$token = $_POST['token'];

$db = new db();

$stmt = $db->prepare("SELECT `user_id` FROM `PhoneConfirmations` WHERE phone=? AND token=?");
$stmt->bind_param("ss", $phone, $token);

$db->exec();
$result = $db->get();

if ($result->num_rows == 0) {
    result(false, "Invalid");
} else {
    result(true, "Valid");
}
?>