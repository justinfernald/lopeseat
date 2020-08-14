<?php
require('api.php');

$user = $GLOBALS['user'];

if ($user == null) {
    result(false, "Not logged in.");
    exit();
}

// delivery count: SELECT COUNT(*) FROM `Orders` WHERE `deliverer`=0
// average rating: SELECT AVG(rating) FROM `Orders` WHERE `deliverer`=0
// average delivery time (arrived - claimed): SELECT SEC_TO_TIME(AVG(TIME_TO_SEC(TIMEDIFF(`arrived`,`claimed`)))) FROM `Orders` WHERE `deliverer`=0
// amount earned:  SELECT SUM(`delivery_fee` + `tip`) FROM `Orders` WHERE `deliverer`=0
// combined: SELECT COUNT(*) AS count ,AVG(rating) AS average_rating ,SEC_TO_TIME(AVG(TIME_TO_SEC(TIMEDIFF(`arrived`,`claimed`)))) AS average_delivery_time, SUM(`delivery_fee` + `tip`) AS amount_earned FROM `Orders` WHERE `deliverer`=0

$db = new db();
$stmt = $db->prepare("SELECT COUNT(*) AS count, AVG(rating) AS average_rating, SEC_TO_TIME(AVG(TIME_TO_SEC(TIMEDIFF(`arrived`,`claimed`)))) AS average_delivery_time, SUM(`delivery_fee` + `tip`) AS amount_earned FROM `Orders` WHERE `deliverer`=?");
$stmt->bind_param("i",$user->id);
$db->exec();


$row = $db->get()->fetch_object();

$message = array(
    "deliveryCount"=>$row->count,
    "averageRating"=>$row->average_rating,
    "averageDeliveryTime"=>$row->average_delivery_time,
    "amountEarned"=>$row->amount_earned
);
result(true, $message)
?>