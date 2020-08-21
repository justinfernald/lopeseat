<?php
require('api.php');

$id = $_GET['id'];

if (!isset($id)) {
    result(false, "no id");
}

$db = new db();
$stmt = $db->prepare("SELECT * FROM Restaurants WHERE `disabled`=0 AND `id`=?");
$stmt->bind_param("i", $id);
$db->exec();
$results = $db->get();

if ($restaurant = $results->fetch_object()) {
    $restaurant->hours = json_decode($restaurant->hours);
    echo json_encode($restaurant);
} else {
    result(false, "not a restaurant");
}
?>