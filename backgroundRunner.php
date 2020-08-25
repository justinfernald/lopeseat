<?php
require('api.php');
$payload = $_GET['payload'];

$fp = fopen('backgroundRunner.log', 'a');
$milliseconds = round(microtime(true) * 1000);
fwrite($fp, "$milliseconds | $payload\n");  
fclose($fp);

echo "true";
?>