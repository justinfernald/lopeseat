<?php
// require('api.php');

// $db = new db();

// $stmt = $db->prepare("SELECT a.amount_available, a.item_id FROM InventoryChanges a 
// INNER JOIN (SELECT item_id, MAX(id) as m_id FROM InventoryChanges GROUP BY item_id) as b on b.m_id = a.id AND a.item_id=117");
// $db->exec();
// $result = $db->get();

// echo $result->fetch_assoc()['amount_available'];

echo var_dump($_POST['isnotreal']);

?>