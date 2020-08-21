<?php
require('api.php');

$phone = $_GET["phone"];
$token = $_GET["token"];

$db = new db();

$stmt = $db->prepare("SELECT id FROM Users WHERE phone=? AND token=? AND confirmed=0");
$stmt->bind_param("ss", $phone, $token);
$db->exec();

$result = $db->get();

$id = 0;

if ($result->num_rows == 0) {
    $row = $result->fetch_assoc();
    $id = $row["id"];

    $stmt = $db->prepare("SELECT * from PhoneConfirmations WHERE phone=? AND token=?");
    $stmt->bind_param("ss",$phone,$token);
    $db->exec();
    $result = $db->get();

    if ($result->num_rows == 0) {
        result(false,"Could not find phone number with that token.");
        exit(0);
    } else {
        $stmt = $db->prepare("DELETE FROM `PhoneConfirmations` WHERE `user_id`=?");
        $stmt->bind_param("i", $GLOBALS['user']->id);
        $db->exec();
    }
}

$stmt = $db->prepare("UPDATE `Users` SET `confirmed`=1, `phone`=? WHERE `id`=?");
$stmt->bind_param("si",$phone,$id);
$db->exec();
result(true);
?>