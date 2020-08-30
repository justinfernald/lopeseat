<?php
require("../api.php");

$db = new db();
$stmt = $db->prepare("SELECT * FROM Orders WHERE submitted=0 AND `state`='completed'");
$db->exec();
$result = $db->get();

$ids = Array();

while ($row = $result->fetch_assoc()) {
    $arrived = strtotime($row['arrived']);
    $dif = (time() - $arrived);
    echo $dif."<br/>";
    echo time()."<br/>";
    echo $arrived."<br/>";
    if ($dif > 2*60) {
        echo "attempt";
        $btResult = $gateway->transaction()->submitForSettlement($row['transaction_id']);
        if ($btResult->success) {
            echo "success";
            array_push($ids, $row['id']);
        } else {
            print_r($btResult->errors);
        }
    }
}

if (count($ids) > 0) {
    $idList = "(".implode(", ", $ids).")";
    echo $idList;
    $stmt = $db->prepare("UPDATE Orders SET submitted=0 WHERE id IN $idList");
    $db->exec();
}
?>