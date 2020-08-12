<?php
require('api.php');

$user = $GLOBALS['user'];

if ($user == null) {
    result(false, "Not logged in.");
    exit();
}

echo json_encode(array("name"=>$user->name,"phone"=>$user->phone,"email"=>$user->email, "deliverer"=>!!$user->deliverer, "studentId"=>$user->student_id));
?>