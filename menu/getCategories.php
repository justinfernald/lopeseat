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
$stmt = $db->prepare("SELECT id,name,image FROM ItemCategories WHERE restaurant_id=? ORDER BY priority DESC");
$stmt->bind_param("i", $rid);
$db->exec();
$results = $db->get();

$categories = [];

while ($category = $results->fetch_object()) {
    array_push($categories, utf8ize($category));
}

echo json_encode($categories);
