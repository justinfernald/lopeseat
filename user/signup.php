<?php
require('../api.php');

use Twilio\Rest\Client;

$db = new db();

$name = $_POST["name"];
$ID_number = $_POST["id"];
$email = $_POST["email"];
$phone = formatPhoneNumber($_POST["phone"]);
$password = $_POST["password"];
$profile_image = $_POST["profileImage"];

$stmt = $db->prepare("SELECT * FROM Users WHERE student_id=? OR email=? OR phone=?");
$stmt->bind_param("sss", $ID_number, $email, $phone);

$db->exec();

$result = $db->get();

if (!(isset($_POST["name"]) && isset($_POST["id"]) && isset($_POST["email"]) && isset($_POST["phone"]) && isset($_POST["password"]) && isset($_POST["profileImage"]))) {
    result(false, "Missing data " . $_POST["name"] . " " . $_POST["id"] . " " . $_POST["email"] . " " . $_POST["phone"] . " " . $_POST["password"] . " " . $_POST["profileImage"]);
    exit();
}

if ($result->num_rows > 0) {
    result(false, "ID, Email, or Phone already exists");
    exit();
}

if (!validPassword($password)) {
    result(false, "Password must be 8 characters longs and include at least one letter and number");
    exit();
}

if (!validEmail($email)) {
    result(false, "You must use a GCU email");
}

$salt = randomToken();
$vCode = randomNum();
$pwd = hash("sha256",$password.$salt);

$stmt = $db->prepare("INSERT INTO `Users` (`student_id`, `name`, `phone`, `email`, `profile_image`, `salt`, `hash`) VALUES (?,?,?,?,?,?,?)");
$stmt->bind_param("sssssss", $ID_number, $name, $phone, $email, $profile_image, $salt, $pwd);
$db->exec();
$user_id = $stmt->insert_id;

$stmt = $db->prepare("INSERT INTO `PhoneConfirmations` (`user_id`, `phone`, `token`) VALUES (?,?,?)");
$stmt->bind_param("iss", $user_id, $phone, $vCode);
$db->exec();

$stmt = $db->prepare("SELECT id FROM `Users` WHERE `student_id`=?");
$stmt->bind_param("s", $ID_number);
$db->exec();
$result = $db->get();
$row = $result->fetch_assoc();

$_SESSION['id'] = $row['id'];

$twilio = new Client($secrets->twilio->sid, $secrets->twilio->token);

$message = $twilio->messages->create($phone, array(
    "body" => "Thank you for joining LopesEat! Your code is $vCode",
    "from" => "+17207456737"
));

result(true);
?>