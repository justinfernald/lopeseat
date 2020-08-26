<?php
require('../api.php');

$db = new db();

$phone = formatPhoneNumber($_GET["phone"]);

$stmt = $db->prepare("SELECT phone FROM Users WHERE phone=?");
$stmt->bind_param("s",$phone);
$db->exec();

$result = $db->get();

echo ($result->num_rows == 0) ? "false" : "true";

?>