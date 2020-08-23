<?php
require('api.php');

$cardLocation = $_GET['cardLocation'];

if (!isset($cardLocation)) {
    result(false, "no card location");
    exit();
}

$db = new db();
$stmt = $db->prepare("SELECT * FROM `HomeScreenCards` WHERE card_location=?");
$stmt->bind_param("s", $cardLocation);
$db->exec();

$result = $db->get();

$cards = [];
while ($row = $result->fetch_object()) {
    $payload = array(
        "id"=>$row->id,
        "title"=>$row->title,
        "description"=>$row->description,
        "tag"=>$row->tag,
        "url"=>$row->url,
        "image"=>$row->image,
        "card_location"=>$row->card_location,
    );
    array_push($cards, $payload);
}

result(true, $cards)
?>