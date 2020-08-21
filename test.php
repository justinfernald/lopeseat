<?php
require('api.php');

$db = new db();
$stmt = $db->prepare("SELECT * FROM Users WHERE id=? AND deliverer=?");
$id = 29;
$deliverer = 1;
$stmt->bind_param("ii", $id, $deliverer);
$db->exec();
$results = $db->get();

while($row = $results->fetch_assoc()) {
    echo $row['name'];
}

?>