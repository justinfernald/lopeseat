<?php
require('api.php');

if (isLoggedIn()) {
    $db = new db();

    $token = $_POST['token'];

    $stmt = $db->prepare("UPDATE Users SET FBToken=? WHERE id=?");
    $stmt->bind_param("si",$token,$_SESSION['id']);
    if ($db->exec()) {
        result(true);
    }
    exit();
}
?>