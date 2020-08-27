<?php
  require(__DIR__.'/vendor/autoload.php');

  header('Access-Control-Allow-Origin: *');
  header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
  header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, X-Requested-With");
  header("Content-Type: application/json");

  $secrets = json_decode(file_get_contents(__DIR__."/config/secrets.json"));

  $gateway = new Braintree_Gateway([
      'environment'=>$secrets->braintree->environment,
      'merchantId' =>$secrets->braintree->merchantId,
      'publicKey' =>$secrets->braintree->publicKey,
      'privateKey' =>$secrets->braintree->privateKey
  ]);

  $paypalClientId = $secrets->paypal->clientId;
  $paypalSecret = $secrets->paypal->secret;

  $GLOBALS['PCI'] = $paypalClientId;
  $GLOBALS['PS'] = $paypalSecret;

  $GLOBALS['sql_user'] = $secrets->sql->user;
  $GLOBALS['sql_pass'] = $secrets->sql->pass;

  $messages = json_decode(file_get_contents(__DIR__."/config/messages.json"));
  $GLOBALS['messages'] = $messages;

  $GLOBALS['serviceAccountPath'] = sprintf("%s/config/service_account.json", __DIR__);

  if ($_POST['apiToken'] !== null) {
    $GLOBALS['user'] = getUserFromToken($_POST['apiToken']);
  }

  function getUser($id) {
    $db = new db();
    $stmt = $db->prepare("SELECT * FROM Users WHERE id=?");
    $stmt->bind_param("s", $id);
    $db->exec();
    $result = $db->get();
    return $result->fetch_object("User");
  }

  function isLoggedIn() {
    return $GLOBALS['user'] != null;
  }

  function result($success, $message = "none") {
      if ($message === "none") {
        $message = $success ? "Success" : "Failed";
      }
      $out = array('success' => $success, 'msg' => $message);
      echo json_encode($out);
      exit();
  }

  function randomToken($n = 13) {
    $chars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $out = "";

    for ($i = 0; $i < $n; $i++) {
      $out .= $chars[rand(0, strlen($chars)-1)];
    }

    return $out;
  }

  function randomNum($n = 6) {
    $digits = "0123456789";
    $out = "";

    for ($i = 0; $i < $n; $i++) {
      $out .= $digits[rand(0, strlen($digits)-1)];
    }

    return $out;
  }

  function formatPhoneNumber($phone) {
    $phone = preg_replace("/[^0-9]/", "", $phone);
    if (strlen($phone) > 10) {
      $phone = substr($phone, -10);
    }
    return $phone;
  }

  function validPassword($password) {
    //echo (strlen($password) >= 8) . " && " . (preg_match("/[A-Za-z]/", $password)) . "&&" . (preg_match("/[0-9]/", $password));
    return (strlen($password) >= 8 && preg_match("/[A-Za-z]/", $password) == 1 && preg_match("/[0-9]/", $password) == 1);
  }

  function getUserFromToken($token) {
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

  function getPaypalToken() {
    $auth = base64_encode($GLOBALS['PCI'] . ":" . $GLOBALS['PS']);
    
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, "https://api.sandbox.paypal.com/v1/oauth2/token");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Content-Type: application/x-www-form-urlencoded",
        "Authorization: Basic " . $auth
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

  function getPayoutStatus($batchId) {
    $paypalToken = getPaypalToken();

    $url = "https://api.sandbox.paypal.com/v1/payments/payouts/$batchId";

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Content-Type: application/json",
        "Authorization: Bearer " . $paypalToken
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

  class CartItem {

    var $id;
    var $user_id;
    var $item_id;
    var $amount;
    var $comment;
    var $options;
    var $items;
    var $restaurant_id;
    var $name;
    var $price;
    var $image;
    var $specialInstructions;

    function getTotal() {
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

  class Cart {

    var $items = [];

    static function loadCart($userId) {
      $cart = new Cart();
      $items = [];
      $db = new db();
      $stmt = $db->prepare("SELECT CartItems.id, CartItems.user_id, CartItems.item_id, CartItems.amount, CartItems.comment, CartItems.options, 
      MenuItems.restaurant_id, MenuItems.name, MenuItems.price, MenuItems.image, MenuItems.items, MenuItems.specialInstructions FROM CartItems 
      INNER JOIN MenuItems
      ON CartItems.user_id=? AND CartItems.item_id = MenuItems.id");
      $stmt->bind_param("i", $userId);
      $db->exec();
      $result = $db->get();

      while($item = $result->fetch_object("CartItem")) {
        array_push($items, $item);
      }
      $cart->items = $items;
      return $cart;
    }

    function getTotal() {
      $total = 0;
      for ($i = 0; $i < sizeof($this->items); $i++) {
        $total += $this->items[$i]->getTotal();
      }
      return $total;
    }

  }

  class OrderItem {

    var $id;
    var $user_id;
    var $order_id;
    var $item_id;
    var $amount;
    var $comment;
    var $options;
    var $items;
    var $restaurant_id;
    var $restaurant_name;
    var $name;
    var $price;
    var $image;

    function getTotal() {
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

  class Order {

    var $items = [];

    static function loadOrder($orderId) {
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

      while($item = $result->fetch_object("OrderItem")) {
          array_push($items, $item);
      }
      $order->items = $items;
      return $order;
    }

  }

  class User {
    var $id;
    var $student_id;
    var $name;
    var $deliverer;
    var $phone;
    var $email;
    var $profile_image;
    var $confirmed;
    var $token;
    var $salt;
    var $hash;
    var $created;
    var $FBToken;
    var $orderIds;

    function getPayoutTotal() {
      $db = new db();
      $stmt = $db->prepare("SELECT * FROM Orders WHERE deliverer=? AND state='completed' AND payoutReceived=0");
      $stmt->bind_param("i", $this->id);
      $db->exec();
      $results = $db->get();
      
      if ($results->num_rows == 0)
        return 0;
      
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

    function updateOrderPayouts($batchId) {
      if (sizeof($this->orderIds) > 0) {
        $db = new db();
        $condition = implode(" OR ", $this->orderIds);
        
        $stmt = $db->prepare("UPDATE Orders SET payoutReceived=1, payoutBatchId=? WHERE $condition");
        $stmt->bind_param("s", $batchId);
        $db->exec();
      }
    }
  }

  class db {

    var $stmt = null;

    function prepare($query) {
      if ($GLOBALS['conn'] === null) {
        $server = "localhost";
        $user = $GLOBALS['sql_user'];
        $pass = $GLOBALS['sql_pass'];
        $db = "zerentha_lopeseat";

        $conn = new mysqli($server, $user, $pass, $db);
        $GLOBALS['conn'] = $conn;

        if ($conn->connect_error) {
          die("Connection failed: " . $conn->connect_error);
        }
      }

      $this->stmt = $GLOBALS['conn']->prepare($query);
      return $this->stmt;
    }

    function exec() {
      $this->stmt->execute();
      if (!$this->stmt->error) {
          return true;
      } else {
          result(false, "SQL Error: " . $this->stmt->error);
          exit();
      }
    }

    function get() {
      return $this->stmt->get_result();
    }

    function close() {
      mysqli_close($this->conn);
    }

  }

  // Request setup

  function handleRequest($handlerFunc, $get, $post) {
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
?>