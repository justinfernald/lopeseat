<?php
require('api.php');

$phone = $_GET["phone"];
$token = $_GET["token"];

$db = new db();

$stmt = $db->prepare("SELECT `user_id` from PhoneConfirmations WHERE phone=? AND token=?");
$stmt->bind_param("ss",$phone,$token);
$db->exec();
$result = $db->get();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $user_id = $row['user_id'];

    $stmt = $db->prepare("UPDATE `Users` SET `confirmed`=1, `phone`=? WHERE `id`=?");
    $stmt->bind_param("si",$phone,$user_id);
    $db->exec();
    
    $stmt = $db->prepare("DELETE FROM `PhoneConfirmations` WHERE `user_id`=?");
    $stmt->bind_param("i", $user_id);
    $db->exec();
    
    result(true);
    exit(0);
}

result(false,"Could not find phone number with that token.");
?>