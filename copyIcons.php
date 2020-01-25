<?php
    require('api.php');

    $prefix = "https://lopeseat.com/REST/icons/";

    // name -> title
    // 

    $db = new db();

    $days = array("Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday");

    $obj = json_decode(file_get_contents("http://gcupublic.blob.core.windows.net/foodvenue/FoodVenueData.json"));
    $venues = $obj->FoodVenues;
    var_dump($venues);
    for ($i = 0; $i < sizeof($venues); $i++) {
        $v = $venues[$i];
        $icon = $prefix.$v->Icon;
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

        $stmt = $db->prepare("INSERT INTO Restaurants (name, description, logo, hours) values (?,?,?,?)");
        $stmt->bind_param("ssss",$name,$desc,$icon,json_encode($hours));
        $db->exec();

        var_dump($hours);
    }

?>