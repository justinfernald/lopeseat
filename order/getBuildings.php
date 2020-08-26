<?php
require('../api.php');

$db = new db();
$stmt = $db->prepare("SELECT * FROM Buildings");
$stmt->bind_param("s",$query);

$buildings = array();

$db->exec();
$results = $db->get();

while ($row = $results->fetch_object()) {
    array_push($buildings, $row);
}

echo json_encode($buildings);

?>