<?php
require('api.php');

echo isRestaurantOpen($_GET['id'], (new DateTime("now", new DateTimeZone("America/Phoenix")))->add(new DateInterval("PT30M"))) ? "true" : "false";

?>