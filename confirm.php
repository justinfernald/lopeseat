<?php
require('api.php');

$phone = $_GET["phone"];
$token = $_GET["token"];

$db = new db();

$stmt = $db->prepare("SELECT id FROM Users WHERE phone=? AND token=?");
$stmt->bind_param("ss", $phone, $token);
$db->exec();

$result = $db->get();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $stmt = $db->prepare("UPDATE `Users` SET `confirmed` = '1' WHERE `id`=?");
    $stmt->bind_param("i",$row["id"]);
    $db->exec();
    result(true);
} else {
    result(false,"Could not find phone number with that token.");
}
?>