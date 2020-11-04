<?php
require '../api.php';

$rid = $_GET['id'];

$db = new db();
$stmt = $db->prepare("SELECT
    ItemCategories.id,
    ItemCategories.name,
    ItemCategories.image,
    Restaurants.name AS restaurantName
FROM
    ItemCategories
INNER JOIN Restaurants ON
    ItemCategories.restaurant_id = Restaurants.id
WHERE
    ItemCategories.id = ?");
$stmt->bind_param("i", $rid);
$db->exec();
$results = $db->get();

if ($category = $results->fetch_object()) {
    echo json_encode($category);
} else {
    echo json_encode(false);

}
