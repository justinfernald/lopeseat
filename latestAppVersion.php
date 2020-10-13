<?php
require('api.php');

$db = new db();

$stmt = $db->prepare("SELECT * FROM `AppVersions` ORDER BY `date` DESC LIMIT 1");
$db->exec();
$result = $db->get();
$row = $result->fetch_assoc();

echo json_encode(array("version" => $row['version'], "requireUpdate" => ($row['update_required'] == 1)));
?>