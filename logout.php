<?php
require('api.php');
$_SESSION['id'] = null;
session_destroy();

if (isset($_POST['apiToken'])) {
    
}

result(true, "Logged out");
?>