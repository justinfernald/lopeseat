<?php
require('../api.php');

if (isLoggedIn()) {
    $db = new db();

    $user = $GLOBALS['user'];
    $token = $_POST['token'];
    $platform = $_POST['platform'];

    $stmt = $db->prepare("UPDATE Users SET FBToken=?,FBPlatform=? WHERE id=?");
    $stmt->bind_param("ssi",$token,$platform,$user->id);
    if ($db->exec()) {
        result(true);
    }
    exit();
} else {
    result(false, "Not logged in");
    exit();
}
?>