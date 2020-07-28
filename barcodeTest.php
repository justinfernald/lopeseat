<?php
include('api.php');
require 'vendor/autoload.php';

$key = $_GET['key'];

if ($key == null) {
    result(false, "No key provided");
    exit();
}

// $user = $GLOBALS['user'];

// if ($user == null) {
//     result(false, "Not logged in");
//     exit();
// }

$generator = new Picqer\Barcode\BarcodeGeneratorHTML();
echo $generator->getBarcode($key, $generator::TYPE_CODE_39);

?>