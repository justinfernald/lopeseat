<?php
require("../api.php");

$db = new db();
$stmt = $db->prepare("SELECT * FROM Orders WHERE submitted=0 AND `state`='completed'");
$db->exec();
$result = $db->get();

$ids = Array();

if ($result->num_rows == 0) {
    echo "true";
}

while ($row = $result->fetch_assoc()) {
    $arrived = strtotime($row['arrived']);
    $dif = (time() - $arrived);
    if ($dif > 60*60) {
        $success = FALSE;
        if ($secrets->braintree->environment == "sandbox") {
            $success = TRUE;
        } else {
            $btResult = $gateway->transaction()->submitForSettlement($row['transaction_id']);
            $success = $btResult->success;
        }
        if ($success) {
            echo "true";
            array_push($ids, $row['id']);
        } else {
            print_r($btResult->errors);
            echo "false";
        }
    }
}

if (count($ids) > 0) {
    if (count($ids) == 1) {
        $stmt = $db->prepare("UPDATE Orders SET submitted=1 WHERE id=?");
        $stmt->bind_param("i",$ids[0]);
    } else {
        $idList = "(".implode(", ", $ids).")";
        $stmt = $db->prepare("UPDATE Orders SET submitted=1 WHERE id IN $idList");
    }
    $db->exec();
}
?>