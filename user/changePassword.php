<?php
require('../api.php');

if (!isLoggedIn()) {
    result(false, "Not logged in");
}

$db = new db();

$stmt = $db->prepare("SELECT `salt`,`hash` FROM `Users` WHERE `id`=?");
$stmt->bind_param("i",$GLOBALS['user']->id);
$db->exec();
$result = $db->get();

$row = $result->fetch_assoc();

$hash = $row['hash'];
$salt = $row['salt'];

$oldPassword = $_POST["currPassword"];
$oldHash = hash("sha256", $oldPassword.$salt);

if ($oldHash !== $hash) {
    result(false, "Current password did not match");
}

$newPassword = $_POST["newPassword"];

if (!validPassword($newPassword)) {
    result(false, "Password must be 8 characters longs and include at least one letter and number");
}

$newSalt = randomToken();
$newHash = hash("sha256",$newPassword.$newSalt);

$stmt = $db->prepare("UPDATE `Users` SET `hash`=?, `salt`=? WHERE id=?");
$stmt->bind_param("ssi", $newHash, $newSalt, $GLOBALS['user']->id);

$db->exec();

result(true);
?>