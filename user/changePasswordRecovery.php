<?php
require('../api.php');

$phone = $_POST['phone'];
$token = $_POST['token'];

$db = new db();

$stmt = $db->prepare("SELECT `user_id` FROM `PhoneConfirmations` WHERE `phone`=? AND `token`=?");
$stmt->bind_param("ss",$phone, $token);
$db->exec();
$result = $db->get();

if ($result->num_rows == 0) {
    result(false, "Incorrect code");
}

$row = $result->fetch_assoc();

$user_id = $row['user_id'];

$newPassword = $_POST["password"];

if (!validPassword($newPassword)) {
    result(false, "Password must be 8 characters longs and include at least one letter and number");
}

$newSalt = randomToken();
$newHash = hash("sha256",$newPassword.$newSalt);

$stmt = $db->prepare("UPDATE `Users` SET `hash`=?, `salt`=? WHERE id=?");
$stmt->bind_param("ssi", $newHash, $newSalt, $user_id);

$db->exec();

$stmt = $db->prepare("DELETE FROM `PhoneConfirmations` WHERE `token`=?");
$stmt->bind_param("s", $token);
$db->exec();

result(true);
?>