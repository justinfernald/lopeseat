<?php
require('api.php');

$db = new db();

$id = formatPhoneNumber($_GET["id"]);

$stmt = $db->prepare("SELECT student_id FROM Users WHERE student_id=?");
$stmt->bind_param("s",$id);
$db->exec();

$result = $db->get();

echo ($result->num_rows == 0) ? "false" : "true";

?>