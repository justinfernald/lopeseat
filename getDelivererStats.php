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

// fullTimeActive (with session being finished): SELECT endSum - startSum FROM (SELECT
    // (SELECT SUM(UNIX_TIMESTAMP(time)) FROM DeliveryMode WHERE user_id = 19 AND start = 1) as startSum,
    // (SELECT SUM(UNIX_TIMESTAMP(time)) FROM DeliveryMode WHERE user_id = 19 AND start = 0) as endSum) as eachSum

// can check if session is done: SELECT start FROM DeliveryMode WHERE user_id=? ORDER BY time DESC LIMIT 1

// get currently active:
/*
SELECT
  DeliveryMode.*
FROM
  (SELECT
     user_id, MAX(time) AS time
   FROM
     DeliveryMode
   GROUP BY
     user_id) AS latest_change
INNER JOIN
  DeliveryMode
ON
  DeliveryMode.user_id = latest_change.user_id AND
  DeliveryMode.time = latest_change.time
WHERE start = 1
*/

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