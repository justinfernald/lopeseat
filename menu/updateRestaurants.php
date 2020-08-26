<?php

$server = "zerenthalrpg.com";
$user = "zerentha_main";
$pass = "61MYql09";
$db = "zerentha_lopeseat";

$conn = new mysqli($server, $user, $pass, $db);

$stmt = $conn->prepare("INSERT INTO `Orders` (`id`, `user_id`, `address`, `deliverer`, `total`, `delivery_fee`, `state`) VALUES (NULL, '1234', 'asdf', '0', '12', '3', 'unclaimed')");
$stmt->execute();

$days = array("Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday");

$obj = json_decode(file_get_contents("http://gcupublic.blob.core.windows.net/foodvenue/FoodVenueData.json"));
$venues = $obj->FoodVenues;
var_dump($venues);
for ($i = 0; $i < sizeof($venues); $i++) {
    $v = $venues[$i];
    $icon = $prefix.$v->Icon;
    $id = $v->Id;
    $name = $v->Title;
    $desc = $v->VenueDescription->Description;
    $currTime = strtotime($v->CurrentTime);
    $hours = array();
    $schedules = $v->BusinessHours->Schedules;

    for ($j = 0; $j < sizeof($schedules); $j++) {
        $sched = $schedules[$j];
        if (property_exists($sched, "StartDate")) {
            foreach($days as $day) {
                $dayObj = array();
                $jHours = $sched->$day->Hours;
                for ($k = 0; $k < sizeof($jHours); $k++) {
                    $h = $jHours[$k];
                    $hArr = array();
                    $hObj = array();
                    $hObj['start'] = $h->Open;
                    $hObj['end'] = $h->Close;
                    $hObj['filters'] = array();
                    array_push($hArr, $hObj);
                    $dayObj['hours'] = $hArr;
                    $hours[$day] = $dayObj;
                }
            }
        }
    }

    $stmt = $conn->prepare("UPDATE Restaurants SET hours=? WHERE id=?");
    $stmt->bind_param("si",json_encode($hours), $id);
    $stmt->execute();

    var_dump($hours);
}
?>