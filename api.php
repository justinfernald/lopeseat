<?php
require __DIR__ . '/vendor/autoload.php';

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, X-Requested-With");
header("Content-Type: application/json");

$secrets = json_decode(file_get_contents(__DIR__ . "/config/secrets.json"));

use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase;
use Kreait\Firebase\Messaging\CloudMessage;
use Twilio\Rest\Client;

$gateway = new Braintree_Gateway([
    'environment' => $secrets->braintree->environment,
    'merchantId' => $secrets->braintree->merchantId,
    'publicKey' => $secrets->braintree->publicKey,
    'privateKey' => $secrets->braintree->privateKey,
]);

$GLOBALS['cartMax'] = 1000;

$paypalClientId = $secrets->paypal->clientId;
$paypalSecret = $secrets->paypal->secret;

$GLOBALS['secrets'] = $secrets;

$GLOBALS['PCI'] = $paypalClientId;
$GLOBALS['PS'] = $paypalSecret;

$GLOBALS['sql_user'] = $secrets->sql->user;
$GLOBALS['sql_pass'] = $secrets->sql->pass;
$GLOBALS['sql_db'] = $secrets->sql->db;

$messages = json_decode(file_get_contents(__DIR__ . "/config/messages.json"));
$GLOBALS['messages'] = $messages;

$GLOBALS['serviceAccountPath'] = __DIR__."/config/service_account.json";

if ($_POST['apiToken'] !== null) {
    $GLOBALS['user'] = getUserFromToken($_POST['apiToken']);
}

function getAmountAvailable($itemId) {
    $db = new db();
    $stmt = $db->prepare("SELECT a.amount_available, a.item_id FROM InventoryChanges a 
    INNER JOIN (SELECT item_id, MAX(id) as m_id FROM InventoryChanges GROUP BY item_id) as b on b.m_id = a.id AND a.item_id=?");
    $stmt->bind_param("i", $itemId);
    $db->exec();
    $result = $db->get();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['amount_available'];
    }
    return 0;
}

function sendNotification($user, $title, $body, $extraData = null) {
    $data = [
    "title" => $title,
    "body" => $body
    ];

    if ($extraData !== null) {
        foreach ($extraData as $key => $value) {
            $data[$key] = $value;
        }
    }

    $db = new db();
    
    $stmt = $db->prepare("SELECT FBToken FROM `Users` WHERE id=?");
    $stmt->bind_param("i", $user->id);
    $db->exec();
    $result = $db->get();
    $row = $result->fetch_assoc();
    $token = $row['FBToken'];

    if ($token != null && strlen($token) > 70) {
        try {
        $serviceAccountPath = sprintf("%s/config/service_account.json", __DIR__);
        $messaging = (new Firebase\Factory())->withServiceAccount($serviceAccountPath)->createMessaging();

        $message = CloudMessage::withTarget('token', $token)->withData($data);

        $notification = Notification::create($title, $body);
        
        $message = $message->withNotification($notification);

        $result = $messaging->send($message);
        } catch (Exception $e) {}
    }
    
    $twilio = new Client($GLOBALS['secrets']->twilio->sid, $GLOBALS['secrets']->twilio->token);
    $phone = $user->phone;
    $messagePhone = $twilio->messages->create($phone, array(
        "body" => "$title : $body",
        "from" => "+17207456737",
    ));
}

function isRestaurantOpen($id, $currentTime = null)
{
    $days = array("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");

    if ($currentTime === null)
      $currentTime = new DateTime("now", new DateTimeZone("America/Phoenix"));
    
    $pastDay = $days[(intval($currentTime->format("w")) + 6) % 7];

    $weekDay = $days[(intval($currentTime->format("w")))];

    $db = new db();
    $stmt = $db->prepare("SELECT `hours` FROM `Restaurants` WHERE id=?");
    $stmt->bind_param("i", $id);

    $db->exec();
    $result = $db->get();

    if ($result->num_rows == 0) {
        // echo "No restaurant found";
        return false;
    }

    $hours = json_decode($result->fetch_assoc()['hours']);

    if (property_exists($hours, $weekDay)) {
        $dayHours = $hours->$weekDay->hours;
        for ($timeIndex = 0; $timeIndex < count($dayHours); $timeIndex++) {
            $times = $dayHours[$timeIndex];
            $splitStartTime = array_map('intval', preg_split("/:/", $times->start));
            $startTime = (new DateTime("now", new DateTimeZone("America/Phoenix")))->setTime($splitStartTime[0], $splitStartTime[1]);
            $endTimeString = $times->end;
            if (strpos($endTimeString, '.') !== false) {
                $endTimeString = preg_split("/\\./", $endTimeString)[1];
            }

            $splitEndTime = array_map('intval', preg_split("/:/", $endTimeString));
            $endTime = (new DateTime("now", new DateTimeZone("America/Phoenix")))->setTime($splitEndTime[0], $splitEndTime[1]);
            if ($startTime->getTimestamp() > $endTime->getTimestamp()) {
                $endTime . add(new DateInterval("P1D"));
            }
            if ($currentTime->getTimestamp() >= $startTime->getTimestamp() && $currentTime->getTimestamp() <= $endTime->getTimestamp()) {
                // echo "In time from today";
                return true;
            }
        }

        if (property_exists($hours, $pastDay)) {
            $pastHours = $hours->$pastDay->hours;

            if (count($pastHours) === 0) {
                // echo "No hours for ".$pastDay;
                return false;
            }

            $lastHour = $pastHours[count($pastHours) - 1];
            $splitEndTime = array_map('intval', preg_split("/:/", $lastHour->end));
            $endTime = (new DateTime())->setTime($splitEndTime[0], $splitEndTime[1]);
            $endTime->sub(new DateInterval("P1D"));
            if ($endTime->getTimestamp() >= $currentTime->getTimestamp()) {
                // echo "In time from yesterday";
                return true;
            }
        }
    }
    // echo "No hours set for ".$weekDay;
    return false;
}

function sendMessage($messageString, $orderId, $sender)
{
    if (strcmp($messageString, "") === 0) {
        result(false, "Empty message");
    }

    $db = new db();
    $user = getUser($sender);

    $stmt = $db->prepare("SELECT * FROM `Orders` WHERE `state`!='completed' AND `id`=? AND (`user_id`=? OR `deliverer`=?)");
    $stmt->bind_param("iii", $orderId, $user->id, $user->id);

    $db->exec();
    $results = $db->get();

    if ($results->num_rows == 0) {
        result(false, "Order not found");
        exit(0);
    }

    $order = $results->fetch_assoc();
    $recipient = $order['user_id'];

    if ($recipient == $user->id) {
        $recipient = $order['deliverer'];
    }

    $stmt = $db->prepare("INSERT INTO Messages (order_id, sender, message, time) VALUES (?,?,?,?)");
    $time = gmdate("Y-m-d H:i:s");
    $stmt->bind_param("iiss", $orderId, $user->id, $messageString, $time);
    $db->exec();

    $stmt = $db->prepare("SELECT FBToken, phone FROM Users WHERE id=?");
    $stmt->bind_param("i", $recipient);
    $db->exec();
    $results = $db->get();

    if ($results->num_rows > 0) {
        $row = $results->fetch_assoc();
        $token = $row['FBToken'];
        $phone = $row['phone'];

        $title = $messages->notifications->message_received->title;
        $body = $messages->notifications->message_received->body;
        $title = str_replace("%sender%", $user->name, $title);
        $title = str_replace("%message%", $messageString, $title);
        $body = str_replace("%sender%", $user->name, $body);
        $body = str_replace("%message%", $messageString, $body);

        $data = [
            "title" => $title,
            "body" => $body,
            "message" => $messageString,
            "sender" => $user->name,
        ];

        
        if ($token != null && $token != "null" && $token != "") {
            $messaging = (new Firebase\Factory())->withServiceAccount($GLOBALS['serviceAccountPath'])->createMessaging();

            $message = CloudMessage::withTarget('token', $token)->withData($data);
            $result = $messaging->send($message);
        }

        $twilio = new Client($GLOBALS['secrets']->twilio->sid, $GLOBALS['secrets']->twilio->token);
        $messagePhone = $twilio->messages->create($phone, array(
            "body" => "New message in LopesEat app - Open the LopesEat app to message your runner",
            "from" => "+17207456737",
        ));
    }
}

function getUser($id)
{
    $db = new db();
    $stmt = $db->prepare("SELECT * FROM Users WHERE id=?");
    $stmt->bind_param("s", $id);
    $db->exec();
    $result = $db->get();
    return $result->fetch_object("User");
}

function isLoggedIn()
{
    return $GLOBALS['user'] != null;
}

function result($success, $message = "none", $doExit = true)
{
    if ($message === "none") {
        $message = $success ? "Success" : "Failed";
    }
    $out = array('success' => $success, 'msg' => $message);
    echo json_encode($out);
    if ($doExit) {
        exit();
    }

}

function randomToken($n = 13)
{
    $chars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $out = "";

    for ($i = 0; $i < $n; $i++) {
        $out .= $chars[rand(0, strlen($chars) - 1)];
    }

    return $out;
}

function randomNum($n = 6)
{
    $digits = "0123456789";
    $out = "";

    for ($i = 0; $i < $n; $i++) {
        $out .= $digits[rand(0, strlen($digits) - 1)];
    }

    return $out;
}

function formatPhoneNumber($phone)
{
    $phone = preg_replace("/[^0-9]/", "", $phone);
    if (strlen($phone) > 10) {
        $phone = substr($phone, -10);
    }
    return $phone;
}

function validEmail($email)
{
    $end = "@my.gcu.edu";
    return substr($email, -strlen($end)) === $end;
}

function validPassword($password)
{
    //echo (strlen($password) >= 8) . " && " . (preg_match("/[A-Za-z]/", $password)) . "&&" . (preg_match("/[0-9]/", $password));
    return (strlen($password) >= 8 && preg_match("/[A-Za-z]/", $password) == 1 && preg_match("/[0-9]/", $password) == 1);
}

function getUserFromToken($token)
{
    $db = new db();
    $stmt = $db->prepare("SELECT user_id FROM apiTokens WHERE token=?");
    $stmt->bind_param("s", $token);
    $db->exec();
    $result = $db->get();
    if ($result->num_rows == 0) {
        return null;
    }
    return getUser($result->fetch_assoc()['user_id']);
}

function getPaypalToken()
{
    $auth = base64_encode($GLOBALS['PCI'] . ":" . $GLOBALS['PS']);

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://api.paypal.com/v1/oauth2/token");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Content-Type: application/x-www-form-urlencoded",
        "Authorization: Basic " . $auth,
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        print "Error: " . curl_error($ch);
        exit();
    }
    curl_close($ch);
    $result = json_decode($result);
    return $result->access_token;
}

function getPayoutStatus($batchId)
{
    $paypalToken = getPaypalToken();

    $url = "https://api.paypal.com/v1/payments/payouts/$batchId";

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Content-Type: application/json",
        "Authorization: Bearer " . $paypalToken,
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        print "Error: " . curl_error($ch);
        exit();
    }
    curl_close($ch);

    $result_json = json_decode($result);

    return $result_json->batch_header->batch_status;
}

class CartItem
{

    public $id;
    public $user_id;
    public $item_id;
    public $amount;
    public $comment;
    public $options;
    public $items;
    public $restaurant_id;
    public $name;
    public $price;
    public $image;
    public $specialInstructions;
    public $amount_available;

    public function getTotal()
    {
        $calculatedPrice = $this->price;
        $itemOptions = json_decode($this->options);

        $itemsObj = json_decode($this->items);

        for ($i = 0; $i < count($itemsObj); $i++) {
            $item = $itemsObj[$i];
            $mItemOptions = $item->options;
            for ($j = 0; $j < count($mItemOptions); $j++) {
                $option = $mItemOptions[$j];
                $choices = $option->choices;
                $choiceIndex = $itemOptions[$i]->{$option->name};
                $choice = $choices->{$choiceIndex};
                if (isset($item) &&
                    isset($option) &&
                    isset($choice) &&
                    isset($choice->cost)) {
                    $calculatedPrice += $choice->cost;
                }
            }
        }

        $total = $calculatedPrice * $this->amount;
        // $total = $this->price * $this->amount;
        return $total;
    }

}

class Cart
{

    public $items = [];
    public $unavailable = [];
    public $track_inv = false;

    public static function loadCart($userId)
    {
        $cart = new Cart();
        $items = [];
        $db = new db();
        $stmt = $db->prepare("SELECT CartItems.id, CartItems.user_id, CartItems.item_id, CartItems.amount, CartItems.comment, 
        CartItems.options, MenuItems.restaurant_id, MenuItems.name, MenuItems.price, MenuItems.image, MenuItems.items, 
        MenuItems.specialInstructions, m.amount_available 
        FROM CartItems 
        INNER JOIN MenuItems ON 
        CartItems.user_id=? AND CartItems.item_id = MenuItems.id 
        INNER JOIN 
        (SELECT a.amount_available, a.item_id FROM InventoryChanges a 
            INNER JOIN (SELECT item_id, MAX(id) AS m_id FROM InventoryChanges GROUP BY item_id) AS b ON b.m_id = a.id) AS m 
            ON m.item_id=CartItems.item_id OR ((SELECT count(*) FROM InventoryChanges i WHERE i.item_id=CartItems.item_id)=0 
            AND m.item_id=0)");
        $stmt->bind_param("i", $userId);
        $db->exec();
        $result = $db->get();

        while ($item = $result->fetch_object("CartItem")) {
            array_push($items, $item);
        }
        $cart->items = $items;

        if (sizeof($cart->items) > 0) {
            $stmt = $db->prepare("SELECT track_inventory FROM Restaurants WHERE id=?");
            $stmt->bind_param("i", $cart->items[0]->restaurant_id);
            $db->exec();
            $result = $db->get();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $cart->track_inv = $row['track_inventory'] == 1 ? true : false;
            }
        }

        return $cart;
    }

    public function getTotal()
    {
        $total = 0;
        for ($i = 0; $i < sizeof($this->items); $i++) {
            $total += $this->items[$i]->getTotal();
        }
        return $total;
    }

    public function count() {
        $count = 0;
        for ($i = 0; $i < count($this->items); $i++) {
            $count += $this->items[$i]->amount;
        }
        return $count;
    }

    public function countItem($item_id) {
        $count = 0;
        for ($i = 0; $i < count($this->items); $i++) {
            if ($this->items[$i]->item_id == $item_id)
                $count += $this->items[$i]->amount;
        }
        return $count;
    }

    public function isAvailable() {
        $unavailable = array();
        $checkedIds = array();
        if (sizeof($this->items) > 0) {
            //Check if restaurant tracks inventory
            if ($this->track_inv) {
                // Make sure cart items don't exceed available inventory
                for ($i = 0; $i < sizeof($this->items); $i++) {
                    $cartItem = $this->items[$i];
                    if (!in_array($cartItem->item_id, $checkedIds)) {
                        if ($cartItem->amount_available < $this->countItem($cartItem->item_id)) {
                            array_push($unavailable, $cartItem);
                        }
                        array_push($checkedIds, $cartItem->item_id);
                    }
                }
            }
        }
        if (sizeof($unavailable) > 0) {
            $this->unavailable = $unavailable;
            return false;
        }
        return true;
    }

}

class OrderItem
{

    public $id;
    public $user_id;
    public $order_id;
    public $item_id;
    public $amount;
    public $comment;
    public $options;
    public $items;
    public $restaurant_id;
    public $restaurant_name;
    public $name;
    public $price;
    public $image;

    public function getTotal()
    {
        $calculatedPrice = $this->price;
        $itemOptions = json_decode($this->options);

        $itemsObj = json_decode($this->items);

        for ($i = 0; $i < count($itemsObj); $i++) {
            $item = $itemsObj[$i];
            $mItemOptions = $item->options;
            for ($j = 0; $j < count($mItemOptions); $j++) {
                $option = $mItemOptions[$j];
                $choices = $option->choices;
                $choiceIndex = $itemOptions[$i]->{$option->name};
                $choice = $choices->{$choiceIndex};
                if (isset($item) &&
                    isset($option) &&
                    isset($choice) &&
                    isset($choice->cost)) {
                    $calculatedPrice += $choice->cost;
                }
            }
        }

        $total = $calculatedPrice * $this->amount;
        // $total = $this->price * $this->amount;
        return $total;
    }

}

class Order
{

    public $items = [];

    public static function loadOrder($orderId)
    {
        $order = new Order();
        $items = [];
        $db = new db();
        $stmt = $db->prepare("SELECT OrderItems.id, OrderItems.order_id, OrderItems.item_id, OrderItems.amount, OrderItems.comment, OrderItems.options,
      MenuItems.restaurant_id, MenuItems.name, MenuItems.price, MenuItems.image, MenuItems.items, Orders.user_id, Restaurants.name as restaurant_name
      FROM OrderItems
      INNER JOIN MenuItems ON OrderItems.order_id=? AND OrderItems.item_id = MenuItems.id
      INNER JOIN Orders ON Orders.id=?
      INNER JOIN Restaurants on MenuItems.restaurant_id=Restaurants.id");
        $stmt->bind_param("ii", $orderId, $orderId);
        $db->exec();
        $result = $db->get();

        while ($item = $result->fetch_object("OrderItem")) {
            array_push($items, $item);
        }
        $order->items = $items;
        return $order;
    }

}

class User
{
    public $id;
    public $student_id;
    public $name;
    public $deliverer;
    public $phone;
    public $email;
    public $profile_image;
    public $confirmed;
    public $token;
    public $salt;
    public $hash;
    public $created;
    public $FBToken;
    public $orderIds;

    public function getPayoutTotal()
    {
        $db = new db();
        $stmt = $db->prepare("SELECT * FROM Orders WHERE deliverer=? AND state='completed' AND payoutReceived=0");
        $stmt->bind_param("i", $this->id);
        $db->exec();
        $results = $db->get();

        if ($results->num_rows == 0) {
            return 0;
        }

        $payoutTotal = 0;
        $payoutOrderIds = array();

        while ($row = $results->fetch_assoc()) {
            $time = strtotime($row['arrived']);
            $now = strtotime(gmdate("Y-m-d H:i:s"));
            if (($now - $time) / 3600 >= 1) {
                $payoutTotal += $row['delivery_fee'] / 2;
                array_push($payoutOrderIds, "id=" . $row['id']);
            }
        }

        $this->orderIds = $payoutOrderIds;

        return $payoutTotal;
    }

    public function updateOrderPayouts($batchId)
    {
        if (sizeof($this->orderIds) > 0) {
            $db = new db();
            $condition = implode(" OR ", $this->orderIds);

            $stmt = $db->prepare("UPDATE Orders SET payoutReceived=1, payoutBatchId=? WHERE $condition");
            $stmt->bind_param("s", $batchId);
            $db->exec();
        }
    }
}

class db
{

    public $stmt = null;

    public function prepare($query)
    {
        if ($GLOBALS['conn'] === null) {
            $server = "localhost";
            $user = $GLOBALS['sql_user'];
            $pass = $GLOBALS['sql_pass'];
            $db = $GLOBALS['sql_db'];

            $conn = new mysqli($server, $user, $pass, $db);
            $GLOBALS['conn'] = $conn;

            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }
        }

        $this->stmt = $GLOBALS['conn']->prepare($query);
        return $this->stmt;
    }

    public function exec()
    {
        $this->stmt->execute();
        if (!$this->stmt->error) {
            return true;
        } else {
            result(false, "SQL Error: " . $this->stmt->error);
            exit();
        }
    }

    public function get()
    {
        return $this->stmt->get_result();
    }

    public function close()
    {
        mysqli_close($this->conn);
    }

}

// Request setup

function handleRequest($handlerFunc, $get, $post)
{
    $db = new db();
    $params = array($db);
    for ($i = 0; $i < sizeof($get); $i++) {
        array_push($params, $_GET[$get[$i]]);
    }
    for ($i = 0; $i < sizeof($post); $i++) {
        array_push($params, $_POST[$post[$i]]);
    }
    call_user_func_array($handlerFunc, $params);
}
