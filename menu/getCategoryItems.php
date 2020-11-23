<?php
require '../api.php';

function utf8ize($d)
{
    if (is_array($d)) {
        foreach ($d as $k => $v) {
            $d[$k] = utf8ize($v);
        }
    } else if (is_object($d)) {
        foreach ($d as $k => $v) {
            $d->$k = utf8ize($v);
        }
    } else {
        return utf8_encode($d);
    }

    return $d;
}

$rid = $_GET['rid'];

$db = new db();
$stmt = $db->prepare("SELECT MenuItems.* FROM `ItemCategoryLink` RIGHT JOIN `MenuItems` ON ItemCategoryLink.item_id = MenuItems.id WHERE ItemCategoryLink.category_id=? ORDER BY ItemCategoryLink.priority DESC");
$stmt->bind_param("i", $rid);
$db->exec();
$results = $db->get();

$items = [];

while ($item = $results->fetch_object()) {
    $item->amount_available = getAmountAvailable($item->id);
    array_push($items, utf8ize($item));
}

echo json_encode($items);
