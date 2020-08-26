<?php
require('../api.php');

$db = new db();

$email = formatPhoneNumber($_GET["email"]);

$stmt = $db->prepare("SELECT email FROM Users WHERE email=?");
$stmt->bind_param("s",$email);
$db->exec();

$result = $db->get();

echo ($result->num_rows == 0) ? "false" : "true";

?>