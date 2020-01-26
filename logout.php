<?php
require('api.php');
$_SESSION['id'] = null;
session_destroy();

if (isset($_POST['apiToken'])) {
    $db = new db();
    $stmt = $db->prepare("DELETE FROM apiTokens WHERE token=?");
    $stmt->bind_param("s", $_POST['apiToken']);
    $db->exec();
}

result(true, "Logged out");
?>