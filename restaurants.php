<?php
require('api.php');

$db = new db();
$stmt = $db->prepare("SELECT * FROM Restaurants");
$db->exec();
$results = $db->get();

$restaurants = [];

while($restaurant = $results->fetch_object()) {
    $restaurant->hours = json_decode($restaurant->hours);
    array_push($restaurants, $restaurant);
}

echo json_encode($restaurants);
?>