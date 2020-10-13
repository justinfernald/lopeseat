<?php
require('api.php');

$title = $_GET['title'];
$body = $_GET['body'];
$user = $_GET['user'];

sendNotification(getUser($user), $title, $body);
?>