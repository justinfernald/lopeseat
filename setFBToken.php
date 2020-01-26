<?php
require('api.php');

if (isLoggedIn()) {
    $db = new db();

    $user = $GLOBALS['user'];
    $token = $_POST['token'];

    $stmt = $db->prepare("UPDATE Users SET FBToken=? WHERE id=?");
    $stmt->bind_param("si",$token,$user->id);
    if ($db->exec()) {
        result(true);
    }
    exit();
}
?>