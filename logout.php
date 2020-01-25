<?php
require('api.php');
$_SESSION['id'] = null;
session_destroy();
result(true, "Logged out");
?>