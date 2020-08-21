<?php
require('api.php');

use Twilio\Rest\Client;

if (!isLoggedIn()) {
    result(false, "Not logged in");
}

$db = new db();

$password = $_POST["password"];
$salt = randomToken();
$pwd = hash("sha256",$password.$salt);

$stmt = $db->prepare("UPDATE `Users` SET `hash`=?, `salt`=? WHERE id=?");
$stmt->bind_param("ssi", $pwd, $salt, $GLOBALS['user']->id);

$db->exec();

result(true);
?>