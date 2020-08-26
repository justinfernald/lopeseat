<?php
require('../api.php');

$user = $GLOBALS['user'];

if ($user == null || $user->deliverer == 0) {
    result(false, "Not logged into delivery account.");
    exit();
}

echo json_encode(array("total"=>$user->getPayoutTotal()));
?>