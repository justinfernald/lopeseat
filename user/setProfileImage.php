<?php
require('../api.php');

$user = $GLOBALS['user'];

if ($user == null) {
    result(false, "Not logged in");
    exit();
}

$db = new db();

$profile_image = $_POST["profileImage"];

$stmt = $db->prepare("UPDATE `Users` SET `profile_image`=? WHERE `id`=?");
$stmt->bind_param("si",$profile_image,$user->id);
$db->exec();

result(true);
?>