<?php
require('../api.php');

if (!isLoggedIn()) {
    result(false, "Not logged in");
}

$user = $GLOBALS['user'];

$db = new db();

$stmt = $db->prepare("SELECT * FROM DeliveryApplications WHERE user_id=?");
$stmt->bind_param("i", $user->id);

$db->exec();
$result = $db->get();

if ($result->num_rows == 0) {
    $stmt = $db->prepare("INSERT INTO DeliveryApplications (user_id) VALUES (?)");
    $stmt->bind_param("i", $user->id);
    $db->exec();
}

$to = $user->email;
$subject = "LopesEat Runner W-9 form";

$msg = "
Hi {$user->name},
<br/><br/>
Please fill out <a href=\"https://dochub.com/joshuapetrie56/Xv7zYW5Rn2jzbkeR2A9egx/w-9-form-pdf?dt=yYBULmxmWZAnyzy_8HeR\">this w-9 form</a>. We will review it and get back to you soon.
<br/><br/>
Thank you,
<br/>
LopesEat team
";

$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

// More headers
$headers .= 'From: <noreply@lopeseat.com>' . "\r\n";

mail($to, $subject, $msg, $headers);

result(true);
?>