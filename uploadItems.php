<?php
require('api.php');

$db = new db();

$menuItems = json_decode(file_get_contents("menuItems.json"));

for ($i = 0; $i < count($menuItems); $i++) {
    $item = $menuItems[$i];
    // echo json_encode($item);
    $items = json_encode($item->items);
    $desc = isset($item->description) ? $item->description : "";
    $img = isset($item->image) ? $item->image : "";
    $stmt = $db->prepare("INSERT INTO MenuItems (`restaurant_id`,`name`,`description`,`price`,`featured`,`specialInstructions`,`items`,`image`) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->bind_param("issdiiss",$item->restaurant_id,$item->name,$desc,$item->price,$item->featured,$item->specialInstructions,$items,$img);
    $db->exec();
}
?>