<?php
require('api.php');

$phone = formatPhoneNumber($_POST['phone']);
$password = $_POST['password'];

$db = new db();

$stmt = $db->prepare("SELECT `confirmed`,`salt`,`hash`,`id` FROM `Users` WHERE `phone`=?");
$stmt->bind_param("s",$phone);
$db->exec();
$result = $db->get();

if ($result->num_rows == 0) {
    result(false, "Unknown phone number");
    exit();
}

$row = $result->fetch_assoc();
$confirmed = $row['confirmed'];
$hash = $row['hash'];
$salt = $row['salt'];
$id = $row['id'];

if ($confirmed == 0) {
    result(false, "Account not confirmed");
    exit();
}

$pwd = hash("sha256",$password.$salt);

if ($pwd === $hash) {
    $_SESSION['id'] = $id;
    result(true);
} else {
    result(false, "Incorrect password");
}
?>