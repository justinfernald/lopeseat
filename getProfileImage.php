<?php
require('api.php');

$user = $GLOBALS['user'];

if ($user == null) {
    result(false, "Not logged in.");
    exit();
}

echo $user->profile_image;
?>