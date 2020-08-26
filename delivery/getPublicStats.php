<?php
require('../api.php');

// active deliveries: SELECT COUNT(*) as count from `Orders` WHERE `state`<>"arrived" AND `state`<>"completed"
// orders in the last hour: SELECT COUNT(*) from `Orders` WHERE UNIX_TIMESTAMP(`placed`)>(UNIX_TIMESTAMP(CURRENT_TIMESTAMP)-(1*60*60))
// histogram: SELECT floor(UNIX_TIMESTAMP(`placed`)/(1*60*60))*(1*60*60) AS time, count(*) AS occurances FROM `Orders` WHERE UNIX_TIMESTAMP(`placed`) > UNIX_TIMESTAMP(CURRENT_TIMESTAMP) - (24*60*60) GROUP BY 1 ORDER BY 1
// histogram (json): SELECT JSON_ARRAYAGG(JSON_OBJECT("time", time, "occurances", occurances)) as histogram_json FROM (SELECT floor(UNIX_TIMESTAMP(`placed`)/(0.5*60*60))*(0.5*60*60) AS time, count(*) AS occurances FROM `Orders` WHERE UNIX_TIMESTAMP(`placed`) > UNIX_TIMESTAMP(CURRENT_TIMESTAMP) - (24*60*60) GROUP BY 1 ORDER BY 1) as histogram
// combined: SELECT (SELECT COUNT(*) from `Orders` WHERE `state`<>"arrived" AND `state`<>"completed") as active_deliveries, (SELECT COUNT(*) from `Orders` WHERE UNIX_TIMESTAMP(`placed`)>(UNIX_TIMESTAMP(CURRENT_TIMESTAMP)-(1*60*60))) as orders_in_hour, (SELECT JSON_ARRAYAGG(JSON_OBJECT("time", time, "occurances", occurances)) FROM (SELECT floor(UNIX_TIMESTAMP(`placed`)/(0.5*60*60))*(0.5*60*60) AS time, count(*) AS occurances FROM `Orders` WHERE UNIX_TIMESTAMP(`placed`) > UNIX_TIMESTAMP(CURRENT_TIMESTAMP) - (24*60*60) GROUP BY 1 ORDER BY 1) as histogram) as histogram_json
    
$db = new db();
$stmt = $db->prepare("SELECT (SELECT COUNT(*) from `Orders` WHERE `state`<>\"arrived\" AND `state`<>\"completed\") as active_deliveries, (SELECT COUNT(*) from `Orders` WHERE UNIX_TIMESTAMP(`placed`)>(UNIX_TIMESTAMP(CURRENT_TIMESTAMP)-(1*60*60))) as orders_in_hour, (SELECT JSON_ARRAYAGG(JSON_OBJECT(\"time\", time, \"occurances\", occurances)) FROM (SELECT floor(UNIX_TIMESTAMP(`placed`)/(0.5*60*60))*(0.5*60*60) AS time, count(*) AS occurances FROM `Orders` WHERE UNIX_TIMESTAMP(`placed`) > UNIX_TIMESTAMP(CURRENT_TIMESTAMP) - (24*60*60) GROUP BY 1 ORDER BY 1) as histogram) as histogram_json");
$db->exec();

$row = $db->get()->fetch_object();

$message = array(
    "activeDeliveries"=>$row->active_deliveries,
    "orderInHour"=>$row->orders_in_hour,
    "histogramData"=>json_decode($row->histogram_json)
);
result(true, $message)
?>