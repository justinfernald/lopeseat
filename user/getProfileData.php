<?php
require('../api.php');

$user = $GLOBALS['user'];

if ($user == null) {
    result(false, "Not logged in.");
    exit();
}

echo json_encode(array("name"=>$user->name,"phoneNumber"=>$user->phone,"email"=>$user->email, "isDeliverer"=>!!$user->deliverer, "studentNumber"=>$user->student_id));
?>