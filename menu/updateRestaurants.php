<?php
$secrets = json_decode(file_get_contents(__DIR__ . "/config/secrets.json"));

function addToHours(&$hours, $schedObj, $day) {
    $dayObj = array();
    $closed = $schedObj->HoursType;
    if ($closed == 0) {
        $jHours = $schedObj->Hours;
        $hArr = array();
        for ($k = 0; $k < sizeof($jHours); $k++) {
            $h = $jHours[$k];
            $hObj = array();
            $hObj['start'] = $h->Open;
            $hObj['end'] = $h->Close;
            $hObj['filters'] = array();
            array_push($hArr, $hObj);
        }
        $dayObj['hours'] = $hArr;
        $hours[$day] = $dayObj;
    }
}

$server = "zerenthalrpg.com";
$user = $secrets->sql->user;
$pass = $secrets->sql->pass;
$db = "zerentha_lopeseat";

$conn = new mysqli($server, $user, $pass, $db);

$days = array("Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday");

$fileCont = file_get_contents("http://gcupublic.blob.core.windows.net/foodvenue/FoodVenueData.json");

echo $fileCont;

$currentDate = new DateTime();
$dateTime = $currentDate->sub(new DateInterval("P".($currentDate->format("w"))."D"));

// echo "Sunday: ".$dateTime->format("Y-m-d")."T00:00:00";

$obj = json_decode($fileCont);
// var_dump($obj);
$venues = $obj->FoodVenues;
// echo "Venues: \n";
// var_dump($venues);
for ($i = 0; $i < sizeof($venues); $i++) {
    $v = $venues[$i];
    $icon = $prefix.$v->Icon;
    $id = $v->Id;
    $name = $v->Title;
    $desc = $v->VenueDescription->Description;
    $currTime = strtotime($v->CurrentTime);
    $hours = array();
    $schedules = $v->BusinessHours->Schedules;

    $priority = -1;

    for ($j = 0; $j < sizeof($schedules); $j++) {
        $sched = $schedules[$j];
        if (property_exists($sched, "StartDate")) {
            foreach($days as $day) {
                addToHours($hours, $sched->$day, $day);
            }
        } else {
            for ($k = 0; $k < count($days); $k++) {
                foreach($sched->Hours as $hour) {
                    if (strcmp($hour->Date, $dateTime->format("Y-m-d")."T00:00:00") === 0 && $sched->Priority > $priority) {
                        addToHours($hours, $hour, $days[intval($dateTime->format("w"))]);
                        break;
                    }
                }
                $dateTime->add(new DateInterval("P1D"));
            }
        }
        $priority = $sched->Priority;
    }

    $hoursString = count($hours) == 0 ? "{}" : json_encode($hours);
    $stmt = $conn->prepare("UPDATE Restaurants SET hours=? WHERE id=?");
    $stmt->bind_param("si", $hoursString, $id);
    $stmt->execute();

    // echo "Hours: \n";
    // var_dump($hours);
}
?>