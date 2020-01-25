<?php
require('api.php');

$clientToken = $gateway->clientToken()->generate();

echo $clientToken;
?>