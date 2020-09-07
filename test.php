<?php
require('api.php');

echo isRestaurantOpen($_GET['id']) ? "true" : "false";

?>