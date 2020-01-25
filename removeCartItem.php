<?php
require('api.php');

$user = $GLOBALS['user'];

if ($user == null) {
    result(false, "Not logged in");
    exit();
}

$id = $_GET['id'];

$db = new db();
$stmt = $db->prepare("DELETE FROM `CartItems` WHERE id=? AND user_id=?");
$stmt->bind_param('ii',$id, $user->id);
if ($db->exec()) {
    result(true);
}
?>